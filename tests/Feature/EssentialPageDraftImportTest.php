<?php

namespace Tests\Feature;

use App\Models\ContentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EssentialPageDraftImportTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    private function command(string $mode, User $user): array
    {
        return ['--'.$mode => true, '--author-email' => $user->email];
    }

    public function test_preflight_does_not_write_pages(): void
    {
        $admin = $this->person();
        $this->artisan('samithai:import-essential-pages', $this->command('check', $admin))
            ->expectsOutput('CONTENT_BUNDLE=6_COMPLETE_PAGES_VERIFIED')
            ->expectsOutput('EXISTING_MATCHING_DRAFTS=0')
            ->expectsOutput('NEW_DRAFTS_TO_CREATE=6')
            ->expectsOutput('READ_ONLY_PREFLIGHT=PASSED')->assertSuccessful();
        $this->assertDatabaseCount('content_entries', 0);
    }

    public function test_import_creates_six_editable_unpublished_cms_pages(): void
    {
        $admin = $this->person();
        $this->artisan('samithai:import-essential-pages', $this->command('apply', $admin))
            ->expectsOutput('ESSENTIAL_PAGE_DRAFT_IMPORT=SUCCESS')
            ->expectsOutput('NEW_DRAFTS_CREATED=6')
            ->expectsOutput('PAGES_PUBLISHED=0')->assertSuccessful();

        $this->assertDatabaseCount('content_entries', 6);
        $this->assertSame(6, ContentEntry::query()
            ->where('kind', 'page')->where('user_id',$admin->id)->where('status','draft')
            ->whereNull('published_at')->whereNull('featured_media_id')->count());

        $expected = [
            'about-samith-ai', 'how-it-works', 'become-a-creator',
            'prompt-quality-standards', 'ai-tools-and-compatibility', 'responsible-ai-usage',
        ];
        $this->assertEqualsCanonicalizing($expected,
            ContentEntry::query()->pluck('slug')->all());

        $this->assertTrue(ContentEntry::query()->get()->every(
            fn(ContentEntry $entry) =>
                strlen($entry->body_html) > 2500
                && str_contains($entry->body_html, '<h2>')
                && !str_contains($entry->body_html, '<script')
                && !str_contains($entry->body_html, 'javascript:')
        ));

        $this->get(route('pages.show', 'about-samith-ai'))->assertNotFound();
        $this->get(route('locale.pages.show', ['locale'=>'en','slug'=>'how-it-works']))
            ->assertNotFound();

        $entry = ContentEntry::query()->where('slug','about-samith-ai')->firstOrFail();
        $this->actingAs($admin)->get(route('dashboard.content', 'pages'))
            ->assertOk()->assertSee('About Samith AI');
        $this->get(route('dashboard.content.preview', $entry))
            ->assertOk()->assertSee('Better AI results start with a better brief.');
    }

    public function test_reimport_is_idempotent_and_rejects_human_edits(): void
    {
        $admin = $this->person();
        $this->artisan('samithai:import-essential-pages', $this->command('apply', $admin))->assertSuccessful();
        $entry = ContentEntry::query()->where('slug','about-samith-ai')->firstOrFail();
        $previousUpdate = $entry->updated_at?->toIso8601String();

        $this->artisan('samithai:import-essential-pages', $this->command('check',$admin))
            ->expectsOutput('EXISTING_MATCHING_DRAFTS=6')
            ->expectsOutput('NEW_DRAFTS_TO_CREATE=0')->assertSuccessful();
        $this->artisan('samithai:import-essential-pages', $this->command('apply',$admin))
            ->expectsOutput('NEW_DRAFTS_CREATED=0')->assertSuccessful();
        $this->assertSame($previousUpdate, $entry->fresh()->updated_at?->toIso8601String());

        $entry->update(['title' => 'Edited by human reviewer']);
        $this->artisan('samithai:import-essential-pages', $this->command('apply',$admin))
            ->assertFailed();
        $this->assertSame('Edited by human reviewer', $entry->fresh()->title);
        $this->assertDatabaseCount('content_entries', 6);
    }

    public function test_cannot_overwrite_existing_live_page_or_conflicting_title(): void
    {
        $admin = $this->person();
        ContentEntry::create([
            'kind'=>'page', 'user_id'=>$admin->id,
            'title'=>'Existing approved company page','slug'=>'about-samith-ai',
            'excerpt'=>'Existing content', 'body_html'=>'<p>Protected publication content</p>',
            'status'=>'published', 'published_at'=>now(),
        ]);
        $this->artisan('samithai:import-essential-pages', $this->command('apply',$admin))
            ->assertFailed();
        $this->assertDatabaseCount('content_entries',1);
        $this->assertDatabaseHas('content_entries', ['slug'=>'about-samith-ai', 'status'=>'published']);

        ContentEntry::query()->delete();
        ContentEntry::create([
            'kind'=>'page', 'user_id'=>$admin->id,
            'title'=>'How It Works','slug'=>'custom-existing-how-it-works',
            'body_html'=>'<p>Existing other page</p>', 'status'=>'draft',
        ]);
        $this->artisan('samithai:import-essential-pages', $this->command('apply',$admin))
            ->assertFailed();
        $this->assertDatabaseCount('content_entries',1);
    }

    public function test_creator_member_or_missing_mode_cannot_import_platform_pages(): void
    {
        foreach (['creator','customer'] as $role) {
            $user = $this->person($role);
            $this->artisan('samithai:import-essential-pages', $this->command('apply',$user))
                ->assertFailed();
        }
        $admin = $this->person('admin');
        $this->artisan('samithai:import-essential-pages', ['--author-email'=>$admin->email])
            ->assertFailed();
        $this->assertDatabaseCount('content_entries',0);
    }

    public function test_existing_non_page_content_is_unchanged(): void
    {
        $admin = $this->person();
        $post = ContentEntry::create([
            'kind'=>'post', 'user_id'=>$admin->id,
            'title'=>'About Samith AI','slug'=>'about-samith-ai',
            'body_html'=>'<p>Original post remains intact.</p>', 'status'=>'published',
            'published_at'=>now(),
        ]);
        $this->artisan('samithai:import-essential-pages', $this->command('apply',$admin))
            ->assertSuccessful();
        $this->assertSame('published', $post->fresh()->status);
        $this->assertDatabaseCount('content_entries',7);
    }
}
