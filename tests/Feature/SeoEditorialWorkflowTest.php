<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\Prompt;
use App\Models\SeoMetadata;
use App\Models\User;
use App\Support\EditorialGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SeoEditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    private function page(User $author, string $kind = 'post', string $status = 'draft', ?string $slug = null): ContentEntry
    {
        return ContentEntry::query()->create([
            'user_id' => $author->getKey(),
            'kind' => $kind,
            'title' => $kind === 'post' ? 'Educational Article' : 'Informational Page',
            'slug' => $slug ?? ($kind === 'post' ? 'educational-article' : 'informational-page'),
            'excerpt' => 'Original editorial summary with validated facts.',
            'body_html' => '<h2>Complete article</h2><p>Original reviewed content and verified facts.</p>',
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    private function prompt(User $user): Prompt
    {
        $category = Category::create([
            'slug' => 'business-productivity', 'name' => 'Business & Productivity', 'is_active' => true,
        ]);
        return Prompt::create([
            'user_id' => $user->id, 'category_id' => $category->id,
            'title' => 'AI Prompt Product', 'slug' => 'ai-prompt-product',
            'summary' => 'A complete product summary for a real prompt.',
            'content' => 'Private prompt text must not be revealed in SEO metadata.',
            'status' => 'draft', 'price' => 0, 'license_type' => 'regular',
        ]);
    }

    public function test_seo_manager_is_admin_only_with_pagination_and_filters(): void
    {
        $owner=$this->user();
        $customer=$this->user('customer');
        for($i=0;$i<12;$i++){
            $entry=$this->page($owner, 'post', 'draft', 'education-post-'.$i);
            $entry->update(['title'=>'Educational Article '.$i]);
        }

        $this->get(route('dashboard.seo'))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('dashboard.seo'))->assertForbidden();
        $this->actingAs($owner)->get(route('dashboard.seo'))->assertOk()
            ->assertSee('SEO Manager')->assertSee('Showing 1–10 of 12 records');
        $this->get(route('dashboard.seo',['page'=>2]))->assertOk()
            ->assertSee('Showing 11–12 of 12 records');
        $this->get(route('dashboard.seo',['kind'=>'invalid']))->assertSessionHasErrors('kind');
    }

    public function test_seo_metadata_validates_paths_and_renders_safe_head_without_public_layout_change(): void
    {
        $owner=$this->user();
        $page=$this->page($owner,'post','published');
        $edit=route('dashboard.seo.edit',['kind'=>'post','id'=>$page->id]);
        $put=route('dashboard.seo.update',['kind'=>'post','id'=>$page->id]);
        $this->actingAs($owner)->get($edit)->assertOk()->assertSee('Meta description');
        $invalid=[
            'seo_title'=>'Test SEO title',
            'meta_description'=>'Useful and factual description for the public article.',
            'canonical_path'=>'https://evil.example/blog/fake',
            'og_title'=>'Share text',
            'og_description'=>'Share description',
            'robots_index'=>1,
        ];
        $this->put($put,$invalid)->assertSessionHasErrors('canonical_path');
        $this->assertDatabaseCount('seo_metadata',0);

        $invalid['canonical_path']='/blog/educational-article';
        $this->put($put,$invalid)->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('seo_metadata', [
            'content_entry_id'=>$page->id,'seo_title'=>'Test SEO title',
            'canonical_path'=>'/blog/educational-article',
        ]);
        $this->get(route('blog.show',$page->slug))->assertOk()
            ->assertSee('<title>Test SEO title</title>',false)
            ->assertSee('name="description"',false)
            ->assertSee('rel="canonical"',false)
            ->assertSee('"@type":"BlogPosting"',false)
            ->assertDontSee('"offers"',false)
            ->assertDontSee('Private prompt text must not',false);
        $this->get(route('dashboard.content.preview',$page))->assertOk()
            ->assertSee('noindex,nofollow')->assertDontSee('"@type":"BlogPosting"',false);
    }

    public function test_prompt_metadata_is_not_public_until_prompt_is_published(): void
    {
        $owner=$this->user();
        $prompt=$this->prompt($owner);
        $this->actingAs($owner)->put(route('dashboard.seo.update',[
            'kind'=>'prompt','id'=>$prompt->id,
        ]),[
            'seo_title'=>'Prompt for productive teams',
            'meta_description'=>'A practical, professional template for repeatable work.',
            'canonical_path'=>'/prompts/ai-prompt-product',
            'robots_index'=>1,
        ])->assertRedirect();
        $this->get(route('prompts.show',$prompt->slug))->assertNotFound();
        $prompt->update(['status'=>'published','published_at'=>now()]);
        $this->get(route('prompts.show',$prompt->slug))->assertOk()
            ->assertSee('Prompt for productive teams')
            ->assertSee('"@type":"Product"',false)
            ->assertDontSee('"offers"',false)
            ->assertDontSee('Private prompt text must not');
    }

    public function test_draft_cannot_publish_without_approved_human_review(): void
    {
        $admin=$this->user();
        $post=$this->page($admin);
        $this->actingAs($admin)->post(route('dashboard.content.publish',$post),['status'=>'published'])
            ->assertSessionHasErrors('status');
        $this->assertSame('draft',$post->fresh()->status);

        $this->post(route('dashboard.editorial.submit',$post))->assertRedirect();
        $this->assertSame('in_review',$post->fresh()->editorial_status);
        $this->post(route('dashboard.editorial.assign',$post),['reviewer_id'=>$admin->id])->assertRedirect();
        $this->post(route('dashboard.editorial.decide',$post),[
            'decision'=>'approve','review_checks'=>['accuracy'=>'1','seo'=>'1','media'=>'1','rights'=>'1'],'notes'=>'I checked the original full article and verified the sources.',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(EditorialGate::approved($post->fresh()));
        $this->post(route('dashboard.content.publish',$post),['status'=>'published'])->assertRedirect();
        $this->assertSame('published',$post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);
        $this->assertGreaterThan(0,$post->editorialRevisions()->count());
    }

    public function test_reviewer_must_be_assigned_and_approval_invalidates_on_edit(): void
    {
        $owner=$this->user();
        $reviewer=$this->user();
        $post=$this->page($owner);
        $this->actingAs($owner)->post(route('dashboard.editorial.submit',$post))->assertRedirect();
        $this->post(route('dashboard.editorial.assign',$post),['reviewer_id'=>$reviewer->id])->assertRedirect();
        $this->post(route('dashboard.editorial.decide',$post),[
            'decision'=>'approve','review_checks'=>['accuracy'=>'1','seo'=>'1','media'=>'1','rights'=>'1'],'notes'=>'Human reviewer verified the draft content.',
        ])->assertForbidden();
        $this->actingAs($reviewer)->post(route('dashboard.editorial.decide',$post),[
            'decision'=>'approve','review_checks'=>['accuracy'=>'1','seo'=>'1','media'=>'1','rights'=>'1'],'notes'=>'I inspected the text and source facts for clarity, accuracy and privacy.',
        ])->assertRedirect();
        $this->assertTrue(EditorialGate::approved($post->fresh()));

        $this->actingAs($owner)->put(route('dashboard.content.update',$post),[
            'title'=>'Educational Article Revised',
            'excerpt'=>'Original editorial summary with validated facts.',
            'body_html'=>'<h2>Complete article</h2><p>Revised text requires renewed human review.</p>',
            'featured_media_id'=>'',
        ])->assertRedirect();
        $this->assertSame('draft',$post->fresh()->editorial_status);
        $this->assertFalse(EditorialGate::approved($post->fresh()));
        $this->post(route('dashboard.content.publish',$post),['status'=>'published'])
            ->assertSessionHasErrors('status');
        $this->assertGreaterThanOrEqual(3,$post->editorialRevisions()->count());
    }

    public function test_schedule_only_publishes_a_due_current_approval_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 08:00:00','UTC'));
        $admin=$this->user();
        $post=$this->page($admin);
        $this->actingAs($admin)->post(route('dashboard.editorial.submit',$post))->assertRedirect();
        $this->post(route('dashboard.editorial.assign',$post),['reviewer_id'=>$admin->id])->assertRedirect();
        $this->post(route('dashboard.editorial.decide',$post),[
            'decision'=>'approve','review_checks'=>['accuracy'=>'1','seo'=>'1','media'=>'1','rights'=>'1'],'notes'=>'Editorial review completed against the latest full draft.',
        ])->assertRedirect();
        $this->post(route('dashboard.editorial.schedule',$post),[
            'scheduled_for'=>'2026-11-11 10:00:00',
        ])->assertRedirect();
        $this->assertSame('scheduled',$post->fresh()->editorial_status);
        $this->artisan('samithai:publish-scheduled-editorial')->assertSuccessful();
        $this->assertSame('draft',$post->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-11 11:00:00','UTC'));
        $this->artisan('samithai:publish-scheduled-editorial')->assertSuccessful();
        $this->assertSame('published',$post->fresh()->status);
        $this->assertSame('approved',$post->fresh()->editorial_status);
        $this->assertNull($post->fresh()->scheduled_for);
        $this->assertDatabaseCount('content_editorial_revisions',5);
        $this->artisan('samithai:publish-scheduled-editorial')->assertSuccessful();
        $this->assertSame(1,\App\Models\AuditEvent::where('action','content.editorial.auto_publish')->count());
    }

    public function test_other_six_blog_drafts_and_prompt_products_are_untouched_by_scheduler(): void
    {
        $admin=$this->user();
        $prompt=$this->prompt($admin);
        $post=$this->page($admin);
        $post->update(['scheduled_for'=>now()->subMinute(),'editorial_status'=>'scheduled']);
        $this->artisan('samithai:publish-scheduled-editorial')->assertSuccessful();
        $this->assertSame('draft',$post->fresh()->status);
        $this->assertSame('draft',$prompt->fresh()->status);
    }

    public function test_editorial_image_assignment_and_human_checklist_are_enforced(): void
    {
        $admin=$this->user();
        $customer=$this->user('customer');
        $post=$this->page($admin);
        $image=MediaAsset::query()->create([
            'user_id'=>$admin->id,'title'=>'Reviewed concept','kind'=>'image',
            'mime'=>'image/webp','file_path'=>'media/test-image.webp',
            'bytes'=>1234,'checksum'=>str_repeat('b',64),
        ]);
        $mediaUrl=route('dashboard.editorial.media',$post);
        $this->actingAs($customer)->post($mediaUrl,[
            'featured_media_id'=>$image->id,'og_media_id'=>$image->id,
        ])->assertForbidden();
        $this->actingAs($admin)->post($mediaUrl,[
            'featured_media_id'=>$image->id,'og_media_id'=>$image->id,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame((int)$image->id,(int)$post->fresh()->featured_media_id);
        $this->assertSame((int)$image->id,(int)$post->fresh()->seo->og_media_id);
        $this->get(route('dashboard.editorial.show',$post))->assertOk()
            ->assertSee('Featured image & social image',false)
            ->assertSee('SEO & content review',false);

        $this->post(route('dashboard.editorial.submit',$post))->assertRedirect();
        $this->post(route('dashboard.editorial.assign',$post),['reviewer_id'=>$admin->id])->assertRedirect();
        $decision=route('dashboard.editorial.decide',$post);
        $this->post($decision,[
            'decision'=>'approve','notes'=>'Reviewed the draft text and originality rights.',
        ])->assertSessionHasErrors('review_checks.accuracy');
        $this->assertSame('in_review',$post->fresh()->editorial_status);
        $this->post($decision,[
            'decision'=>'approve','notes'=>'Reviewed the draft text, SEO, image and licensing evidence.',
            'review_checks'=>['accuracy'=>'1','seo'=>'1','media'=>'1','rights'=>'1'],
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(EditorialGate::approved($post->fresh()));

        $this->post($mediaUrl,[
            'featured_media_id'=>'','og_media_id'=>'',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('draft',$post->fresh()->editorial_status);
        $this->assertFalse(EditorialGate::approved($post->fresh()));

        $post->update(['status'=>'published','published_at'=>now()]);
        $this->post($mediaUrl,[
            'featured_media_id'=>$image->id,'og_media_id'=>$image->id,
        ])->assertSessionHasErrors('featured_media_id');
        $this->assertNull($post->fresh()->featured_media_id);
    }


}
