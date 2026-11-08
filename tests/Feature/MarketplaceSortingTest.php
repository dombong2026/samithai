<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceSortingTest extends TestCase
{
    use RefreshDatabase;

    private function prompt(User $creator, Category $category, string $title, float $price, int $daysAgo): Prompt
    {
        return Prompt::create([
            'user_id' => $creator->id,
            'category_id' => $category->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.abs($daysAgo),
            'summary' => 'Searchable prompt for marketplace sorting acceptance.',
            'content' => 'PRIVATE_SORTING_CONTENT',
            'price' => $price,
            'status' => 'published',
            'license_type' => 'regular',
            'published_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_marketplace_sorts_published_prompts_by_newest_oldest_title_and_price(): void
    {
        $creator = User::factory()->create();
        $category = Category::create(['name' => 'Productivity','slug' => 'productivity','is_active' => true]);
        $this->prompt($creator, $category, 'Zebra workflow', 0, 5);
        $this->prompt($creator, $category, 'Alpha assistant', 30, 1);
        $this->prompt($creator, $category, 'Middle framework', 10, 3);

        $this->get(route('prompts.index'))->assertOk()
            ->assertSeeInOrder(['Alpha assistant', 'Middle framework', 'Zebra workflow']);
        $this->get(route('prompts.index', ['sort' => 'oldest']))->assertOk()
            ->assertSeeInOrder(['Zebra workflow', 'Middle framework', 'Alpha assistant']);
        $this->get(route('prompts.index', ['sort' => 'title_az']))->assertOk()
            ->assertSeeInOrder(['Alpha assistant', 'Middle framework', 'Zebra workflow']);
        $this->get(route('prompts.index', ['sort' => 'price_asc']))->assertOk()
            ->assertSeeInOrder(['Zebra workflow', 'Middle framework', 'Alpha assistant']);
        $this->get(route('prompts.index', ['sort' => 'price_desc']))->assertOk()
            ->assertSeeInOrder(['Alpha assistant', 'Middle framework', 'Zebra workflow']);
    }

    public function test_sorting_composes_with_free_price_and_keyword_filters(): void
    {
        $creator = User::factory()->create();
        $category = Category::create(['name' => 'Productivity','slug' => 'productivity','is_active' => true]);
        $this->prompt($creator, $category, 'Zebra free helper', 0, 3);
        $this->prompt($creator, $category, 'Alpha free helper', 0, 2);
        $this->prompt($creator, $category, 'Middle paid helper', 19, 1);

        $this->get(route('prompts.index', [
            'price' => 'free', 'q' => 'helper', 'sort' => 'title_az',
        ]))->assertOk()
            ->assertSeeInOrder(['Alpha free helper', 'Zebra free helper'])
            ->assertDontSee('Middle paid helper');
    }

    public function test_sorting_rejects_invalid_values_and_still_hides_drafts(): void
    {
        $creator = User::factory()->create();
        $category = Category::create(['name' => 'Writing','slug' => 'writing','is_active' => true]);
        $draft = $this->prompt($creator, $category, 'Hidden draft', 0, 0);
        $draft->update(['status' => 'draft', 'published_at' => null]);

        $this->get(route('prompts.index', ['sort' => 'DROP TABLE']))->assertSessionHasErrors('sort');
        $this->get(route('prompts.index', ['sort' => 'price_asc']))
            ->assertOk()->assertDontSee('Hidden draft');
    }
}
