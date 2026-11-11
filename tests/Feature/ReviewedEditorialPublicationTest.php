<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewedEditorialPublicationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    private function seedDrafts(User $admin): void
    {
        foreach ([
            'business-productivity', 'education-teaching', 'coding-development',
            'marketing-social-media', 'ecommerce-sales', 'data-research',
        ] as $slug) {
            Category::query()->create([
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'description' => 'Authorized category.',
                'is_active' => true,
            ]);
        }
        $this->artisan('samithai:import-essential-pages', [
            '--apply' => true, '--author-email' => $admin->email,
        ])->assertSuccessful();
        $this->artisan('samithai:import-educational-blogs', [
            '--apply' => true, '--author-email' => $admin->email,
        ])->assertSuccessful();
        $this->assertDatabaseCount('content_entries', 14);
    }

    private function arguments(User $user, string $mode, ?string $confirmation = null): array
    {
        $params = ['--reviewer-email' => $user->email, '--'.$mode => true];
        if ($confirmation !== null) $params['--confirm'] = $confirmation;
        return $params;
    }

    public function test_check_requires_admin_and_is_read_only(): void
    {
        $owner = $this->admin();
        $this->seedDrafts($owner);
        $this->artisan('samithai:publish-reviewed-content', $this->arguments($owner,'check'))
            ->expectsOutput('SOURCE_AND_CMS_CONTENT=EXACT_MATCH')
            ->expectsOutput('EDITORIAL_PAGES_DRAFT=6')
            ->expectsOutput('EDITORIAL_PAGES_PUBLISHED=0')
            ->expectsOutput('INTRO_BLOG_DRAFT=2')
            ->expectsOutput('INTRO_BLOG_PUBLISHED=0')
            ->expectsOutput('REMAINING_SIX_BLOG_DRAFTS=VERIFIED')
            ->expectsOutput('EDITORIAL_RELEASE_READ_ONLY_PREFLIGHT=PASSED')
            ->assertSuccessful();
        $this->assertSame(14, ContentEntry::where('status','draft')->count());
        $this->assertDatabaseCount('audit_events',0);
        $other=$this->admin('creator');
        $this->artisan('samithai:publish-reviewed-content', $this->arguments($other,'check'))
            ->assertFailed();
        $this->artisan('samithai:publish-reviewed-content', [
            '--reviewer-email' => $owner->email,
        ])->assertFailed();
        $this->artisan('samithai:publish-reviewed-content', [
            '--reviewer-email' => $owner->email,'--check'=>true,'--publish-pages'=>true,
        ])->assertFailed();
    }

    public function test_pages_must_be_approved_then_articles_publish_in_order(): void
    {
        $owner=$this->admin();
        $this->seedDrafts($owner);
        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-pages'))->assertFailed();
        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-first-article','PUBLISH-FIRST-REVIEWED-ARTICLE'))
            ->assertFailed();
        $this->assertDatabaseCount('audit_events',0);
        $this->assertSame(14, ContentEntry::where('status','draft')->count());

        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-pages','PUBLISH-SIX-REVIEWED-PAGES'))
            ->expectsOutput('EDITORIAL_PUBLICATION_STAGE=SUCCESS')
            ->expectsOutput('NEWLY_PUBLISHED_RECORDS=6')->assertSuccessful();

        $this->assertSame(6, ContentEntry::where('kind','page')->where('status','published')->count());
        $this->assertSame(8, ContentEntry::where('kind','post')->where('status','draft')->count());
        $this->assertSame(6, AuditEvent::where('action','content.status')->count());
        $this->get(route('pages.show','about-samith-ai'))->assertOk()
            ->assertSee('Better AI results start with a better brief');
        $this->get(route('locale.pages.show',['locale'=>'en','slug'=>'responsible-ai-usage']))->assertOk();
        $this->get(route('blog.show','what-is-an-ai-prompt-beginners-guide'))->assertNotFound();

        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-second-article','PUBLISH-SECOND-REVIEWED-ARTICLE'))
            ->assertFailed();
        $this->assertSame(8, ContentEntry::where('kind','post')->where('status','draft')->count());

        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-first-article','PUBLISH-FIRST-REVIEWED-ARTICLE'))
            ->expectsOutput('NEWLY_PUBLISHED_RECORDS=1')->assertSuccessful();
        $this->get(route('blog.show','what-is-an-ai-prompt-beginners-guide'))->assertOk()
            ->assertSee('The five building blocks of a useful prompt');
        $this->get(route('blog.show','how-to-write-better-prompts-for-ai-assistants'))->assertNotFound();

        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-second-article','PUBLISH-SECOND-REVIEWED-ARTICLE'))
            ->expectsOutput('NEWLY_PUBLISHED_RECORDS=1')->assertSuccessful();
        $this->get(route('blog.show','how-to-write-better-prompts-for-ai-assistants'))->assertOk()
            ->assertSee('Step 1: Define one primary outcome');
        $this->get(route('blog.index'))->assertOk()
            ->assertSee('What Is an AI Prompt?')
            ->assertSee('How to Write Better Prompts');

        $this->assertSame(6, ContentEntry::where('kind','post')->where('status','draft')->count());
        $this->assertSame(2, ContentEntry::where('kind','post')->where('status','published')->count());
        $this->assertDatabaseCount('audit_events',8);
        $this->artisan('samithai:publish-reviewed-content',$this->arguments($owner,'check'))
            ->expectsOutput('EDITORIAL_PAGES_PUBLISHED=6')
            ->expectsOutput('INTRO_BLOG_PUBLISHED=2')
            ->expectsOutput('REMAINING_SIX_BLOG_DRAFTS=VERIFIED')
            ->assertSuccessful();
    }

    public function test_repeat_publication_is_idempotent_without_extra_audit_events(): void
    {
        $owner=$this->admin();
        $this->seedDrafts($owner);
        $action=$this->arguments($owner,'publish-pages','PUBLISH-SIX-REVIEWED-PAGES');
        $this->artisan('samithai:publish-reviewed-content',$action)->assertSuccessful();
        $first=ContentEntry::where('slug','about-samith-ai')->firstOrFail();
        $publishedAt=$first->published_at?->toIso8601String();
        $this->artisan('samithai:publish-reviewed-content',$action)
            ->expectsOutput('NEWLY_PUBLISHED_RECORDS=0')->assertSuccessful();
        $this->assertSame($publishedAt,$first->fresh()->published_at?->toIso8601String());
        $this->assertDatabaseCount('audit_events',6);
    }

    public function test_content_drift_blocks_batch_without_partial_publication(): void
    {
        $owner=$this->admin();
        $this->seedDrafts($owner);
        ContentEntry::where('slug','how-it-works')->update(['excerpt'=>'Unreviewed edit.']);
        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-pages','PUBLISH-SIX-REVIEWED-PAGES'))
            ->assertFailed();
        $this->assertSame(14, ContentEntry::where('status','draft')->count());
        $this->assertDatabaseCount('audit_events',0);
    }

    public function test_disabled_linked_public_features_block_publication(): void
    {
        $owner=$this->admin();
        $this->seedDrafts($owner);
        SiteSetting::query()->create(['key'=>'frontend_page.enabled.support','value'=>'0']);
        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-pages','PUBLISH-SIX-REVIEWED-PAGES'))
            ->assertFailed();
        $this->assertSame(14, ContentEntry::where('status','draft')->count());
        SiteSetting::where('key','frontend_page.enabled.support')->update(['value'=>'1']);
        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-pages','PUBLISH-SIX-REVIEWED-PAGES'))
            ->assertSuccessful();
        SiteSetting::query()->create(['key'=>'frontend_page.enabled.blog','value'=>'0']);
        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-first-article','PUBLISH-FIRST-REVIEWED-ARTICLE'))
            ->assertFailed();
        $this->assertSame(8, ContentEntry::where('kind','post')->where('status','draft')->count());
    }

    public function test_remaining_six_articles_must_not_already_be_public(): void
    {
        $owner=$this->admin();
        $this->seedDrafts($owner);
        ContentEntry::where('slug','20-practical-ai-prompts-for-small-businesses')->update([
            'status'=>'published','published_at'=>now(),
        ]);
        $this->artisan('samithai:publish-reviewed-content',
            $this->arguments($owner,'publish-pages','PUBLISH-SIX-REVIEWED-PAGES'))
            ->assertFailed();
        $this->assertSame(0, ContentEntry::where('kind','page')->where('status','published')->count());
    }
}
