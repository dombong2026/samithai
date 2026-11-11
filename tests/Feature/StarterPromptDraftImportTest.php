<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarterPromptDraftImportTest extends TestCase
{
    use RefreshDatabase;

    private function author(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function options(User $author, string $mode): array
    {
        return ['--author-email' => $author->email, '--'.$mode => true];
    }

    public function test_check_is_read_only_and_data_bundle_has_thirty_full_prompts(): void
    {
        $author = $this->author();
        $this->artisan('samithai:import-starter-drafts', $this->options($author, 'check'))
            ->expectsOutput('CONTENT_BUNDLE=30_PRODUCTS_7_CATEGORIES_VALID')
            ->expectsOutput('DRAFTS_ALREADY_MATCHING=0')
            ->expectsOutput('DRAFTS_TO_CREATE=30')
            ->expectsOutput('READ_ONLY_PREFLIGHT=PASSED')
            ->assertSuccessful();

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('prompts', 0);
    }

    public function test_import_creates_thirty_unpublished_zero_price_owner_scoped_drafts(): void
    {
        $author = $this->author();
        $this->artisan('samithai:import-starter-drafts', $this->options($author, 'apply'))
            ->expectsOutput('STARTER_DRAFT_IMPORT=SUCCESS')
            ->expectsOutput('NEW_DRAFTS_CREATED=30')
            ->expectsOutput('PUBLIC_PROMPTS_PUBLISHED=0')
            ->assertSuccessful();

        $this->assertDatabaseCount('categories', 7);
        $this->assertDatabaseCount('prompts', 30);
        $this->assertSame(30, Prompt::query()->where('user_id', $author->id)->where('status', 'draft')
            ->whereNull('published_at')->where('price', 0)->where('license_type', 'regular')->count());
        $this->assertSame(0, Prompt::published()->count());

        $counts = Category::query()->withCount('prompts')->get()->pluck('prompts_count', 'slug')->all();
        $this->assertSame([
            'marketing-social-media' => 6,
            'education-teaching' => 5,
            'business-productivity' => 5,
            'coding-development' => 5,
            'ai-image-video' => 4,
            'ecommerce-sales' => 3,
            'data-research' => 2,
        ], $counts);
        $this->assertTrue(Prompt::query()->get()->every(
            fn (Prompt $prompt) => strlen($prompt->content) > 700
                && str_contains($prompt->content, 'REQUIRED OUTPUT')
                && str_contains($prompt->content, 'QUALITY & ETHICS RULES')
        ));

        $this->get(route('prompts.index'))->assertOk()
            ->assertDontSee('30-Day Social Media Content Planner');
        $this->get(route('home'))->assertOk()
            ->assertDontSee('30-Day Social Media Content Planner');
        $this->actingAs($author)->get(route('dashboard.prompts'))
            ->assertOk()->assertSee('30-Day Social Media Content Planner');
    }

    public function test_repeated_import_is_idempotent_and_does_not_overwrite(): void
    {
        $author = $this->author('creator');
        $this->artisan('samithai:import-starter-drafts', $this->options($author, 'apply'))
            ->assertSuccessful();

        $original = Prompt::query()->where('slug', 'starter-2026-30-day-social-media-content-planner')->firstOrFail();
        $firstUpdatedAt = $original->updated_at?->toIso8601String();

        $this->artisan('samithai:import-starter-drafts', $this->options($author, 'check'))
            ->expectsOutput('DRAFTS_ALREADY_MATCHING=30')
            ->expectsOutput('DRAFTS_TO_CREATE=0')
            ->assertSuccessful();
        $this->artisan('samithai:import-starter-drafts', $this->options($author, 'apply'))
            ->expectsOutput('NEW_DRAFTS_CREATED=0')
            ->assertSuccessful();

        $this->assertDatabaseCount('prompts', 30);
        $this->assertSame($firstUpdatedAt, $original->fresh()->updated_at?->toIso8601String());

        $original->update(['summary' => 'Human edited; preserve this draft']);
        $this->artisan('samithai:import-starter-drafts', $this->options($author, 'apply'))
            ->assertFailed();
        $this->assertSame('Human edited; preserve this draft', $original->fresh()->summary);
        $this->assertDatabaseCount('prompts', 30);
    }

    public function test_conflict_or_unqualified_user_does_not_create_any_starter_records(): void
    {
        $customer = $this->author('customer');
        $this->artisan('samithai:import-starter-drafts', $this->options($customer, 'apply'))
            ->assertFailed();
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('prompts', 0);

        $admin = $this->author('admin');
        $category = Category::create([
            'name' => 'Legacy', 'slug' => 'legacy', 'description' => 'Existing category', 'is_active' => true,
        ]);
        Prompt::create([
            'user_id' => $admin->id, 'category_id' => $category->id,
            'slug' => 'starter-2026-30-day-social-media-content-planner',
            'title' => 'Existing customer-owned content',
            'summary' => 'Never overwritten by a batch importer.',
            'content' => 'User-created content that should remain unchanged.',
            'price' => 25, 'status' => 'published', 'published_at' => now(),
            'license_type' => 'regular',
        ]);
        $this->artisan('samithai:import-starter-drafts', $this->options($admin, 'check'))->assertFailed();
        $this->artisan('samithai:import-starter-drafts', $this->options($admin, 'apply'))->assertFailed();
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('prompts', 1);
        $this->assertSame('Existing customer-owned content',
            Prompt::where('slug', 'starter-2026-30-day-social-media-content-planner')->firstOrFail()->title);
    }

    public function test_existing_named_category_is_reused_without_duplicate_categories(): void
    {
        $author = $this->author();
        $existing = Category::create([
            'name' => 'Marketing & Social Media', 'slug' => 'marketing',
            'description' => 'Existing marketplace grouping', 'is_active' => true,
        ]);
        $this->artisan('samithai:import-starter-drafts', $this->options($author, 'apply'))
            ->assertSuccessful();
        $this->assertDatabaseCount('categories', 7);
        $this->assertSame(6, Prompt::where('category_id', $existing->id)->count());
        $this->assertDatabaseMissing('categories', ['slug' => 'marketing-social-media']);
    }

    public function test_inactive_category_and_missing_mode_fail_closed(): void
    {
        $author = $this->author();
        Category::create([
            'name' => 'Data & Research', 'slug' => 'data-research',
            'description' => 'Disabled in production', 'is_active' => false,
        ]);
        $this->artisan('samithai:import-starter-drafts', $this->options($author, 'apply'))
            ->assertFailed();
        $this->assertDatabaseCount('prompts', 0);
        $this->artisan('samithai:import-starter-drafts', ['--author-email' => $author->email])
            ->assertFailed();
        $this->assertDatabaseCount('prompts', 0);
    }
}
