<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function category(): Category
    {
        return Category::create([
            'name' => 'Writing', 'slug' => 'writing', 'description' => 'Writing prompts', 'is_active' => true,
        ]);
    }

    private function publishedPrompt(User $creator, Category $category, float $price = 0): Prompt
    {
        return Prompt::create([
            'user_id' => $creator->id, 'category_id' => $category->id,
            'title' => 'Practical writing assistant', 'slug' => 'practical-writing-assistant-'.uniqid(),
            'summary' => 'A detailed writing assistant prompt.',
            'content' => 'SECRET_LICENSED_PROMPT_BODY',
            'price' => $price, 'status' => 'published', 'license_type' => 'regular',
            'published_at' => now(),
        ]);
    }

    public function test_public_catalog_hides_private_prompt_text(): void
    {
        $creator = User::factory()->create();
        $prompt = $this->publishedPrompt($creator, $this->category());

        $this->get(route('prompts.show', $prompt))
            ->assertOk()
            ->assertSee($prompt->title)
            ->assertDontSee('SECRET_LICENSED_PROMPT_BODY');

        $this->get(route('account.library.show', $prompt))->assertRedirect(route('login'));
    }

    public function test_a_customer_cannot_use_administrator_routes(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('dashboard.categories'))->assertForbidden();
        $this->actingAs($customer)->post(route('dashboard.categories.store'), [
            'name' => 'Unsafe', 'description' => '', 'is_active' => '1',
        ])->assertForbidden();
    }

    public function test_creator_draft_review_publication_and_edit_permissions(): void
    {
        $category = $this->category();
        $creator = User::factory()->create();
        $creator->forceFill(['role' => 'creator'])->save();
        $customer = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $payload = [
            'title' => 'My first useful prompt', 'category_id' => $category->id,
            'summary' => 'Improve daily writing tasks.', 'content' => 'PRIVATE_BODY',
            'price' => '0', 'license_type' => 'regular',
        ];

        $this->actingAs($creator)->post(route('account.prompts.store'), $payload)->assertRedirect();
        $prompt = Prompt::firstOrFail();
        $this->assertSame('draft', $prompt->status);

        $this->actingAs($customer)->put(route('account.prompts.update', $prompt), $payload)->assertForbidden();
        $this->actingAs($creator)->post(route('account.prompts.submit', $prompt))->assertRedirect();
        $this->assertSame('pending', $prompt->fresh()->status);

        $this->actingAs($admin)->post(route('dashboard.prompts.moderate', $prompt), [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertSame('published', $prompt->fresh()->status);
        $this->get(route('prompts.show', $prompt))->assertOk();
    }

    public function test_free_checkout_grants_entitlement_but_paid_checkout_does_not(): void
    {
        $creator = User::factory()->create();
        $customer = User::factory()->create();
        $category = $this->category();
        $free = $this->publishedPrompt($creator, $category);
        $paid = $this->publishedPrompt($creator, $category, 9.99);

        $this->actingAs($customer)->post(route('cart.add', $free))->assertRedirect();
        $this->actingAs($customer)->post(route('checkout'))->assertRedirect(route('account.library'));
        $this->assertDatabaseHas('prompt_entitlements', ['user_id' => $customer->id, 'prompt_id' => $free->id]);
        $this->actingAs($customer)->get(route('account.library.show', $free))
            ->assertOk()->assertSee('SECRET_LICENSED_PROMPT_BODY');

        $this->actingAs($customer)->post(route('cart.add', $paid))->assertRedirect();
        $this->actingAs($customer)->post(route('checkout'))->assertSessionHasErrors('cart');
        $this->assertDatabaseMissing('prompt_entitlements', ['user_id' => $customer->id, 'prompt_id' => $paid->id]);
    }
}
