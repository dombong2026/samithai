<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\PromptReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    private function prompt(User $creator): Prompt
    {
        $category = Category::create(['name' => 'Productivity','slug' => 'productivity','is_active' => true]);
        return Prompt::create([
            'user_id' => $creator->id, 'category_id' => $category->id,
            'title' => 'Work summary prompt', 'slug' => 'work-summary-prompt',
            'summary' => 'A reusable work summary assistant.',
            'content' => 'PRIVATE_CREATOR_PROMPT_BODY',
            'price' => '0', 'status' => 'pending', 'license_type' => 'regular',
        ]);
    }

    public function test_rejection_requires_admin_and_written_feedback_and_cannot_be_repeated(): void
    {
        $creator = $this->person('creator');
        $admin = $this->person('admin');
        $prompt = $this->prompt($creator);
        $url = route('dashboard.prompts.moderate', $prompt);

        $this->actingAs($creator)->post($url, [
            'decision' => 'reject', 'review_note' => 'Not allowed to moderate.',
        ])->assertForbidden();

        $this->actingAs($admin)->post($url, ['decision' => 'reject'])
            ->assertSessionHasErrors('review_note');
        $this->assertDatabaseCount('prompt_reviews', 0);
        $this->assertSame('pending', $prompt->fresh()->status);

        $this->post($url, ['decision' => 'reject', 'review_note' => 'short'])
            ->assertSessionHasErrors('review_note');

        $reason = 'Please add a clear input example and describe the expected output.';
        $this->post($url, ['decision' => 'reject','review_note' => $reason])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('rejected', $prompt->fresh()->status);
        $this->assertDatabaseHas('prompt_reviews', [
            'prompt_id' => $prompt->id, 'actor_id' => $admin->id,
            'action' => 'rejected', 'note' => $reason,
        ]);

        $this->post($url, ['decision' => 'approve'])->assertStatus(422);
        $this->assertSame(1, PromptReview::count());
    }

    public function test_history_is_accessible_only_to_owner_and_admin(): void
    {
        $creator = $this->person('creator');
        $other = $this->person('creator');
        $customer = $this->person('customer');
        $admin = $this->person('admin');
        $prompt = $this->prompt($creator);
        $reason = 'Please clarify all steps before publishing this prompt.';

        $this->actingAs($admin)->post(route('dashboard.prompts.moderate', $prompt), [
            'decision' => 'reject', 'review_note' => $reason,
        ])->assertRedirect();

        $url = route('account.prompts.reviews', $prompt);
        $this->actingAs($creator)->get(route('account.prompts'))
            ->assertOk()->assertSee('Reviewer feedback');
        $this->get($url)->assertOk()->assertSee($reason);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee($reason);
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($customer)->get($url)->assertForbidden();

        $this->get(route('home'))->assertOk()->assertDontSee($reason);
        $this->get(route('prompts.index'))->assertOk()->assertDontSee($reason);
    }

    public function test_revision_resubmission_and_approval_create_a_timeline(): void
    {
        $creator = $this->person('creator');
        $admin = $this->person('admin');
        $prompt = $this->prompt($creator);

        $this->actingAs($admin)->post(route('dashboard.prompts.moderate', $prompt), [
            'decision' => 'reject',
            'review_note' => 'Please add specific output examples and clarify the result format.',
        ])->assertRedirect();

        $this->actingAs($creator)->put(route('account.prompts.update', $prompt), [
            'title' => $prompt->title, 'category_id' => $prompt->category_id,
            'summary' => $prompt->summary, 'content' => 'PRIVATE_UPDATED_CREATOR_CONTENT',
            'price' => '0', 'license_type' => 'regular',
        ])->assertRedirect();

        $this->assertSame('draft', $prompt->fresh()->status);
        $this->post(route('account.prompts.submit', $prompt))->assertRedirect();
        $this->assertSame('pending', $prompt->fresh()->status);
        $this->assertSame('submitted', $prompt->fresh()->latestReview->action);
        $this->post(route('account.prompts.submit', $prompt))->assertStatus(422);

        $this->actingAs($admin)->post(route('dashboard.prompts.moderate', $prompt), [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertSame('published', $prompt->fresh()->status);
        $this->assertSame(
            ['rejected', 'submitted', 'approved'],
            $prompt->reviewEvents()->orderBy('id')->pluck('action')->all()
        );

        $this->actingAs($creator)->get(route('account.prompts.reviews', $prompt))
            ->assertOk()
            ->assertSee('Please add specific output examples')
            ->assertSee('Approved')
            ->assertDontSee('PRIVATE_UPDATED_CREATOR_CONTENT');
    }

    public function test_feedback_is_escaped_in_creator_pages(): void
    {
        $creator = $this->person('creator');
        $admin = $this->person('admin');
        $prompt = $this->prompt($creator);
        $feedback = "Please revise this <script>alert('review')</script> section before approval.";

        $this->actingAs($admin)->post(route('dashboard.prompts.moderate', $prompt), [
            'decision' => 'reject', 'review_note' => $feedback,
        ])->assertRedirect();

        $this->actingAs($creator)->get(route('account.prompts.reviews', $prompt))
            ->assertOk()
            ->assertDontSee("<script>alert('review')</script>", false)
            ->assertSee('&lt;script&gt;', false);
        $this->get(route('account.prompts'))
            ->assertOk()
            ->assertDontSee("<script>alert('review')</script>", false);
    }

    public function test_rejection_dialog_appears_only_in_admin_prompt_manager(): void
    {
        $creator = $this->person('creator');
        $admin = $this->person('admin');
        $this->prompt($creator);

        $this->actingAs($admin)->get(route('dashboard.prompts', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Reject with feedback')
            ->assertSee('Return prompt for revision');

        $this->actingAs($creator)->get(route('account.prompts'))
            ->assertOk()->assertDontSee('Reject with feedback');
    }
}
