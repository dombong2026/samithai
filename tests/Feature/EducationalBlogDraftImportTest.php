<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EducationalBlogDraftImportTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORY_SLUGS = [
        'business-productivity', 'education-teaching',
        'marketing-social-media', 'ecommerce-sales',
        'coding-development', 'data-research',
    ];

    private function admin(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    private function createCategories(): void
    {
        foreach (self::CATEGORY_SLUGS as $slug) {
            Category::create([
                'slug' => $slug,
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'description' => 'Existing approved product category.',
                'is_active' => true,
            ]);
        }
    }

    private function commandArgs(User $user, string $mode): array
    {
        return ['--author-email' => $user->email, '--'.$mode => true];
    }

    public function test_read_only_preflight_validates_eight_full_articles_and_linked_categories(): void
    {
        $admin = $this->admin();
        $this->createCategories();

        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'check'))
            ->expectsOutput('EDUCATIONAL_ARTICLES=8_FULL_DRAFTS_VERIFIED')
            ->expectsOutput('CATEGORIES_LINKED=VERIFIED')
            ->expectsOutput('EXISTING_MATCHING_BLOG_DRAFTS=0')
            ->expectsOutput('NEW_BLOG_DRAFTS_TO_CREATE=8')
            ->expectsOutput('READ_ONLY_BLOG_PREFLIGHT=PASSED')->assertSuccessful();

        $this->assertDatabaseCount('content_entries', 0);
        $this->assertDatabaseCount('categories', 6);
    }

    public function test_import_creates_eight_complete_posts_without_publishing(): void
    {
        $admin = $this->admin();
        $this->createCategories();

        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'apply'))
            ->expectsOutput('EDUCATIONAL_BLOG_DRAFT_IMPORT=SUCCESS')
            ->expectsOutput('NEW_BLOG_DRAFTS_CREATED=8')
            ->expectsOutput('BLOG_ARTICLES_PUBLISHED=0')->assertSuccessful();

        $this->assertDatabaseCount('content_entries', 8);
        $this->assertSame(8, ContentEntry::query()->where('kind','post')
            ->where('user_id',$admin->id)->where('status','draft')
            ->whereNull('published_at')->whereNull('featured_media_id')->count());

        $this->assertTrue(ContentEntry::query()->get()->every(fn (ContentEntry $post) =>
            strlen($post->body_html) > 5000
            && str_contains($post->body_html, '<h2>')
            && str_contains($post->body_html, 'https://samithai.com/categories/')
            && !str_contains($post->body_html,'<script')
        ));

        $this->get(route('blog.index'))->assertOk()
            ->assertDontSee('What Is an AI Prompt? A Beginner');
        $this->get(route('blog.show','what-is-an-ai-prompt-beginners-guide'))->assertNotFound();
        $this->get(route('locale.blog.show', [
            'locale' => 'en',
            'slug' => '20-practical-ai-prompts-for-small-businesses',
        ]))->assertNotFound();

        $this->actingAs($admin)->get(route('dashboard.content', 'posts'))->assertOk()
            ->assertSee('What Is an AI Prompt? A Beginner')
            ->assertSee('How to Test and Improve an AI Prompt');
        $first = ContentEntry::query()->where('slug','what-is-an-ai-prompt-beginners-guide')->firstOrFail();
        $this->get(route('dashboard.content.preview', $first))->assertOk()
            ->assertSee('The five building blocks of a useful prompt');
    }

    public function test_repeat_import_skips_exact_matches_and_rejects_manual_revisions(): void
    {
        $admin = $this->admin();
        $this->createCategories();
        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'apply'))
            ->assertSuccessful();

        $first = ContentEntry::where('slug','what-is-an-ai-prompt-beginners-guide')->firstOrFail();
        $stamp = $first->updated_at?->toIso8601String();

        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'check'))
            ->expectsOutput('EXISTING_MATCHING_BLOG_DRAFTS=8')
            ->expectsOutput('NEW_BLOG_DRAFTS_TO_CREATE=0')
            ->assertSuccessful();
        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'apply'))
            ->expectsOutput('NEW_BLOG_DRAFTS_CREATED=0')->assertSuccessful();
        $this->assertSame($stamp, $first->fresh()->updated_at?->toIso8601String());

        $first->update(['excerpt' => 'Manually reviewed and updated by the administrator.']);
        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'apply'))
            ->assertFailed();
        $this->assertSame('Manually reviewed and updated by the administrator.',
            $first->fresh()->excerpt);
        $this->assertDatabaseCount('content_entries', 8);
    }

    public function test_conflicting_published_slug_or_title_refuses_all_changes(): void
    {
        $admin = $this->admin();
        $this->createCategories();
        ContentEntry::create([
            'kind' => 'post', 'user_id' => $admin->id,
            'title' => 'Existing editorial article',
            'slug' => 'what-is-an-ai-prompt-beginners-guide',
            'body_html' => '<p>Approved original content.</p>',
            'status' => 'published', 'published_at' => now(),
        ]);
        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'apply'))
            ->assertFailed();
        $this->assertDatabaseCount('content_entries', 1);
        $this->assertDatabaseHas('content_entries', [
            'slug' => 'what-is-an-ai-prompt-beginners-guide', 'status' => 'published',
        ]);

        ContentEntry::query()->delete();
        ContentEntry::create([
            'kind' => 'post', 'user_id' => $admin->id,
            'title' => 'How to Test and Improve an AI Prompt: A Practical Quality Checklist',
            'slug' => 'different-existing-slug',
            'body_html' => '<p>Another human-authored article.</p>', 'status' => 'draft',
        ]);
        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'check'))
            ->assertFailed();
        $this->assertDatabaseCount('content_entries', 1);
    }

    public function test_missing_or_inactive_categories_block_import_without_writes(): void
    {
        $admin = $this->admin();
        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'apply'))
            ->assertFailed();
        $this->assertDatabaseCount('content_entries', 0);

        $this->createCategories();
        Category::where('slug','marketing-social-media')->update(['is_active' => false]);
        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'apply'))
            ->assertFailed();
        $this->assertDatabaseCount('content_entries', 0);
    }

    public function test_non_admin_and_no_mode_cannot_import(): void
    {
        $this->createCategories();
        foreach (['customer','creator'] as $role) {
            $person = $this->admin($role);
            $this->artisan('samithai:import-educational-blogs', $this->commandArgs($person, 'apply'))
                ->assertFailed();
        }
        $admin = $this->admin();
        $this->artisan('samithai:import-educational-blogs', [
            '--author-email' => $admin->email,
        ])->assertFailed();
        $this->assertDatabaseCount('content_entries', 0);
    }

    public function test_import_does_not_modify_unrelated_existing_content(): void
    {
        $admin = $this->admin();
        $this->createCategories();
        $page = ContentEntry::create([
            'kind' => 'page', 'user_id' => $admin->id, 'status' => 'published',
            'published_at' => now(), 'slug' => 'how-it-works', 'title' => 'How It Works',
            'body_html' => '<p>Original page from Page Manager.</p>',
        ]);
        $this->artisan('samithai:import-educational-blogs', $this->commandArgs($admin, 'apply'))
            ->assertSuccessful();
        $this->assertSame('published', $page->fresh()->status);
        $this->assertDatabaseCount('content_entries', 9);
    }

    public function test_source_retains_seo_metadata_and_outlines_without_schema_changes(): void
    {
        foreach ([
            '01-fundamentals.json',
            '02-business-and-teaching.json',
            '03-website-and-social.json',
            '04-quality-and-agents.json',
        ] as $file) {
            $pack = json_decode(file_get_contents(base_path(
                'database/content/educational-blog-2026-11/'.$file
            )), true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount(2, $pack['articles']);
            foreach ($pack['articles'] as $article) {
                $this->assertNotEmpty($article['seo_title']);
                $this->assertNotEmpty($article['meta_description']);
                $this->assertGreaterThanOrEqual(900, $article['word_count_estimate']);
                $this->assertGreaterThanOrEqual(5, count($article['outline']));
            }
        }
    }
}
