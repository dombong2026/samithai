<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\PromptEntitlement;
use App\Models\User;
use App\Support\RecoveryInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SavedPromptsAndCreatorInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'customer'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    private function prompt(User $creator, string $title, string $status = 'published'): Prompt
    {
        $category = Category::firstOrCreate(['slug' => 'creative'], [
            'name' => 'Creative', 'is_active' => true,
        ]);

        return Prompt::create([
            'user_id' => $creator->id,
            'category_id' => $category->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.str()->random(5),
            'summary' => 'Customer-safe prompt preview.',
            'content' => 'PRIVATE_LICENSED_PROMPT_TEXT',
            'status' => $status,
            'price' => 0,
            'license_type' => 'regular',
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    public function test_guest_cannot_save_and_draft_is_never_saved_or_listed(): void
    {
        $creator = $this->user('creator');
        $customer = $this->user();
        $published = $this->prompt($creator, 'Public marketing prompt');
        $draft = $this->prompt($creator, 'Secret unreleased prompt', 'draft');

        $this->post(route('prompts.save', $published))->assertRedirect(route('login'));
        $this->get(route('account.saved'))->assertRedirect(route('login'));

        $this->actingAs($customer)->post(route('prompts.save', $draft))->assertNotFound();
        $this->assertDatabaseCount('prompt_saves', 0);

        $this->post(route('prompts.save', $published))->assertRedirect();
        $this->get(route('account.saved'))->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($draft->title)
            ->assertDontSee('PRIVATE_LICENSED_PROMPT_TEXT');

        $this->get(route('prompts.index', ['sort' => 'most_saved']))->assertOk()
            ->assertDontSee($draft->title)
            ->assertDontSee('PRIVATE_LICENSED_PROMPT_TEXT');
    }

    public function test_saves_are_user_scoped_repeatable_and_removable_without_changing_licenses(): void
    {
        $creator = $this->user('creator');
        $customerA = $this->user();
        $customerB = $this->user();
        $prompt = $this->prompt($creator, 'Daily scheduling prompt');

        $this->actingAs($customerA)->post(route('prompts.save', $prompt))->assertRedirect();
        $this->post(route('prompts.save', $prompt))->assertRedirect();
        $this->assertDatabaseCount('prompt_saves', 1);
        $this->assertDatabaseCount('prompt_entitlements', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->get(route('account.saved'))->assertOk()->assertSee($prompt->title);
        $this->actingAs($customerB)->get(route('account.saved'))->assertOk()
            ->assertDontSee($prompt->title);
        $this->post(route('prompts.unsave', $prompt))->assertRedirect();
        $this->assertDatabaseCount('prompt_saves', 1);

        $this->actingAs($customerA)->delete(route('prompts.unsave', $prompt))->assertRedirect();
        $this->delete(route('prompts.unsave', $prompt))->assertRedirect();
        $this->assertDatabaseCount('prompt_saves', 0);
    }

    public function test_unpublished_prompt_is_hidden_from_saved_list_but_can_be_unsaved(): void
    {
        $creator = $this->user('creator');
        $customer = $this->user();
        $prompt = $this->prompt($creator, 'Previously published assistant');

        $this->actingAs($customer)->post(route('prompts.save', $prompt))->assertRedirect();
        $prompt->update(['status' => 'draft', 'published_at' => null]);

        $this->get(route('account.saved'))->assertOk()->assertDontSee($prompt->title);
        $this->get(route('prompts.show', $prompt))->assertNotFound();
        $this->delete(route('prompts.unsave', $prompt))->assertRedirect();
        $this->assertDatabaseCount('prompt_saves', 0);
    }

    public function test_trending_sort_and_saved_filter_show_only_published_prompts(): void
    {
        $creator = $this->user('creator');
        $one = $this->prompt($creator, 'One favorite item');
        $two = $this->prompt($creator, 'Two favorite item');
        $draft = $this->prompt($creator, 'Hidden favorited draft', 'draft');
        $userA = $this->user();
        $userB = $this->user();

        $this->actingAs($userA)->post(route('prompts.save', $one))->assertRedirect();
        $this->post(route('prompts.save', $two))->assertRedirect();
        $this->actingAs($userB)->post(route('prompts.save', $two))->assertRedirect();

        // Previously saved drafts can remain in storage but never become discoverable.
        DB::table('prompt_saves')->insert([
            'user_id' => $userA->id,
            'prompt_id' => $draft->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get(route('prompts.index', ['sort' => 'most_saved']))->assertOk()
            ->assertSeeInOrder([$two->title, $one->title])
            ->assertDontSee($draft->title);

        $this->actingAs($userA)->get(route('account.saved', ['q' => 'Two favorite']))
            ->assertOk()->assertSee($two->title)->assertDontSee($one->title)
            ->assertDontSee($draft->title);
    }

    public function test_creator_insights_are_private_and_only_count_real_activity(): void
    {
        $creator = $this->user('creator');
        $otherCreator = $this->user('creator');
        $customer = $this->user();
        $viewer = $this->user();
        $own = $this->prompt($creator, 'My insightful published prompt');
        $unpublished = $this->prompt($creator, 'My private draft', 'draft');
        $notMine = $this->prompt($otherCreator, 'Another creator product');

        $this->actingAs($customer)->post(route('prompts.save', $own))->assertRedirect();
        $this->post(route('prompts.save', $notMine))->assertRedirect();
        $this->actingAs($viewer)->post(route('prompts.save', $own))->assertRedirect();

        DB::table('prompt_saves')->insert([
            'user_id' => $customer->id,
            'prompt_id' => $unpublished->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get(route('account.creator.insights'))->assertForbidden();
        $this->actingAs($creator)->get(route('account.creator.insights'))
            ->assertOk()
            ->assertSee('My insightful published prompt')
            ->assertDontSee('Another creator product')
            ->assertDontSee('PRIVATE_LICENSED_PROMPT_TEXT')
            ->assertSee('Saved by users')
            ->assertSee('License claims');

        $this->assertSame(2, DB::table('prompt_saves')
            ->join('prompts', 'prompts.id', '=', 'prompt_saves.prompt_id')
            ->where('prompts.user_id', $creator->id)
            ->where('prompts.status', 'published')->count());
        $this->actingAs($otherCreator)->get(route('account.creator.insights'))
            ->assertOk()->assertDontSee('My insightful published prompt');
        $this->actingAs($customer)->get(route('account.creator.insights'))->assertForbidden();
    }

    public function test_saves_are_part_of_integrity_inventory_and_cascade_on_prompt_removal(): void
    {
        $creator = $this->user('creator');
        $customer = $this->user();
        $prompt = $this->prompt($creator, 'Cascading cleanups');

        $before = RecoveryInventory::capture();
        $this->actingAs($customer)->post(route('prompts.save', $prompt))->assertRedirect();
        $after = RecoveryInventory::capture();
        $this->assertSame('samith-recovery-inventory/v4', $after['format']);
        $this->assertContains('prompt_saves.count', RecoveryInventory::differences($before, $after));
        $this->assertContains('prompt_saves.content_hmac_sha256', RecoveryInventory::differences($before, $after));
        $this->assertTrue(RecoveryInventory::healthy($after));

        $prompt->delete();
        $this->assertDatabaseCount('prompt_saves', 0);
    }
}
