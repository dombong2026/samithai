<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\EditorialGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeoEditorialSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role'=>'admin'])->save();
        return $admin;
    }

    private function post(User $user, string $status = 'draft'): ContentEntry
    {
        return ContentEntry::query()->create([
            'user_id'=>$user->getKey(),
            'kind'=>'post',
            'title'=>'Practical AI Content Tutorial',
            'slug'=>'practical-ai-content-tutorial',
            'excerpt'=>'Readable and accurate editorial introduction.',
            'body_html'=>'<h2>Editorial example</h2><p>Reusable, factual source material.</p>',
            'status'=>$status,
            'published_at'=>$status==='published' ? now() : null,
        ]);
    }

    public function test_editable_seo_preview_uses_public_canonical_not_dashboard_url(): void
    {
        $admin=$this->admin();
        $post=$this->post($admin,'published');
        $response=$this->actingAs($admin)->get(route('dashboard.seo.edit',[
            'kind'=>'post', 'id'=>$post->getKey(),
        ]));
        $response->assertOk()->assertSee(url('/blog/'.$post->slug),false)
            ->assertDontSee('rel="canonical" href="'.route('dashboard.seo.edit',[
                'kind'=>'post','id'=>$post->getKey(),
            ]).'"',false);
    }

    public function test_json_ld_can_be_disabled_without_hiding_og_or_canonical_metadata(): void
    {
        $admin=$this->admin();
        $post=$this->post($admin,'published');
        $this->actingAs($admin)->put(route('dashboard.seo.update',[
            'kind'=>'post','id'=>$post->getKey(),
        ]),[
            'seo_title'=>'Reviewed AI Tutorial',
            'meta_description'=>'A tested editorial source description that does not imply guaranteed outcomes.',
            'canonical_path'=>'/blog/practical-ai-content-tutorial',
            'structured_data_enabled'=>0,
            'robots_index'=>1,
        ])->assertRedirect()->assertSessionHas('success');
        $this->get(route('blog.show',$post->slug))->assertOk()
            ->assertSee('<title>Reviewed AI Tutorial</title>',false)
            ->assertSee('property="og:title"',false)
            ->assertSee('rel="canonical"',false)
            ->assertDontSee('"@type":"BlogPosting"',false);

        $this->actingAs($admin)->put(route('dashboard.seo.update',[
            'kind'=>'post','id'=>$post->getKey(),
        ]),[
            'structured_data_enabled'=>1,
            'robots_index'=>1,
        ])->assertRedirect();
        $this->get(route('blog.show',$post->slug))->assertOk()
            ->assertSee('"@type":"BlogPosting"',false);
    }

    public function test_draft_social_image_is_private_until_attached_content_is_published(): void
    {
        Storage::fake('local');
        $admin=$this->admin();
        $post=$this->post($admin);
        $path='media/'.$admin->getKey().'/private-original.webp';
        Storage::disk('local')->put($path,'fake-image-bytes');
        $asset=MediaAsset::query()->create([
            'user_id'=>$admin->getKey(),
            'title'=>'Original visual for social sharing',
            'kind'=>'image',
            'mime'=>'image/webp',
            'file_path'=>$path,
            'bytes'=>16,
            'checksum'=>hash('sha256','fake-image-bytes'),
        ]);
        $post->seo()->create(['og_media_id'=>$asset->getKey(),'robots_index'=>true]);

        $this->get(route('media.serve',$asset))->assertForbidden();
        $post->update(['status'=>'published','published_at'=>now()]);
        $this->get(route('media.serve',$asset))->assertOk()
            ->assertHeader('Content-Type','image/webp');
        $post->update(['status'=>'draft','published_at'=>null]);
        $this->get(route('media.serve',$asset))->assertForbidden();
    }

    public function test_revision_restoration_is_private_and_resets_approval(): void
    {
        $admin=$this->admin();
        $post=$this->post($admin);
        $this->actingAs($admin)->post(route('dashboard.editorial.submit',$post))->assertRedirect();
        $this->post(route('dashboard.editorial.assign',$post),[
            'reviewer_id'=>$admin->getKey(),
        ])->assertRedirect();
        $this->post(route('dashboard.editorial.decide',$post),[
            'decision'=>'approve',
            'notes'=>'I checked the text, all source claims, the rights, and the outgoing links.',
        ])->assertRedirect();
        $this->assertTrue(EditorialGate::approved($post->fresh()));

        $this->put(route('dashboard.content.update',$post),[
            'title'=>'Revised AI Content Tutorial',
            'excerpt'=>'Readable and accurate editorial introduction.',
            'body_html'=>'<h2>Editorial example</h2><p>Changed article after human approval.</p>',
        ])->assertRedirect();
        $before=$post->editorialRevisions()->where('action','before_edit')->firstOrFail();
        $this->get(route('dashboard.editorial.revision',[
            'entry'=>$post,'revision'=>$before,
        ]))->assertOk()->assertSee('Previously saved article markup');

        $this->post(route('dashboard.editorial.restore',[
            'entry'=>$post,'revision'=>$before,
        ]))->assertRedirect(route('dashboard.editorial.show',$post));
        $this->assertSame('Practical AI Content Tutorial',$post->fresh()->title);
        $this->assertSame('draft',$post->fresh()->status);
        $this->assertSame('draft',$post->fresh()->editorial_status);
        $this->assertFalse(EditorialGate::approved($post->fresh()));
        $this->post(route('dashboard.content.publish',$post),[
            'status'=>'published',
        ])->assertSessionHasErrors('status');
        $this->assertSame(1,$post->editorialRevisions()->where('action','after_restore')->count());
    }

    public function test_revisions_cannot_be_cross_read_or_restored_while_published(): void
    {
        $admin=$this->admin();
        $one=$this->post($admin);
        $two=ContentEntry::query()->create([
            'user_id'=>$admin->getKey(),'kind'=>'post','title'=>'Another article',
            'slug'=>'another-article','body_html'=>'<p>Original information.</p>','status'=>'draft',
        ]);
        EditorialGate::revision($two,$admin->getKey(),'create');
        $revision=$two->editorialRevisions()->firstOrFail();
        $this->actingAs($admin)->get(route('dashboard.editorial.revision',[
            'entry'=>$one,'revision'=>$revision,
        ]))->assertNotFound();
        $this->post(route('dashboard.editorial.restore',[
            'entry'=>$one,'revision'=>$revision,
        ]))->assertNotFound();
        $two->update(['status'=>'published','published_at'=>now()]);
        $this->post(route('dashboard.editorial.restore',[
            'entry'=>$two,'revision'=>$revision,
        ]))->assertSessionHasErrors('revision');
    }

    public function test_scheduled_publication_can_be_cancelled_without_auto_publishing(): void
    {
        $admin=$this->admin();
        $post=$this->post($admin);
        $this->actingAs($admin)->post(route('dashboard.editorial.submit',$post))->assertRedirect();
        $this->post(route('dashboard.editorial.assign',$post),['reviewer_id'=>$admin->getKey()])->assertRedirect();
        $this->post(route('dashboard.editorial.decide',$post),[
            'decision'=>'approve','notes'=>'I have reviewed the complete current draft for accuracy and rights.',
        ])->assertRedirect();
        $this->post(route('dashboard.editorial.schedule',$post),[
            'scheduled_for'=>now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect();
        $this->assertSame('scheduled',$post->fresh()->editorial_status);
        $this->post(route('dashboard.editorial.cancel',$post))->assertRedirect();
        $this->assertSame('approved',$post->fresh()->editorial_status);
        $this->assertNull($post->fresh()->scheduled_for);
        $this->assertTrue(EditorialGate::approved($post->fresh()));
        $this->artisan('samithai:publish-scheduled-editorial')->assertSuccessful();
        $this->assertSame('draft',$post->fresh()->status);
    }

    public function test_published_post_featured_image_cannot_change_without_re_review(): void
    {
        $admin=$this->admin();
        $post=$this->post($admin,'published');
        $media=MediaAsset::query()->create([
            'user_id'=>$admin->getKey(),'title'=>'Unreviewed replacement',
            'kind'=>'image','mime'=>'image/webp',
        ]);
        $this->actingAs($admin)->put(route('dashboard.content.update',$post),[
            'title'=>$post->title,'excerpt'=>$post->excerpt,
            'body_html'=>$post->body_html,'featured_media_id'=>$media->getKey(),
        ])->assertSessionHasErrors('body_html');
        $this->assertNull($post->fresh()->featured_media_id);
    }
}
