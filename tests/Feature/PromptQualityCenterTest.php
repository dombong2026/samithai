<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\PromptQualityReview;
use App\Models\User;
use App\Support\PromptQualityGate;
use App\Support\StarterPromptScenarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class PromptQualityCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function person(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function prompt(User $owner, string $slug = 'starter-2026-30-day-social-media-content-planner'): Prompt
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'marketing-social-media'],
            ['name' => 'Marketing & Social Media', 'is_active' => true]
        );

        return Prompt::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => '30-Day Social Media Content Planner',
            'slug' => $slug,
            'summary' => 'A realistic calendar generator that remains private during review.',
            'content' => 'PRIVATE_STARTER_CONTENT_REQUIRING_REAL_TESTS',
            'price' => '0.00',
            'license_type' => 'regular',
            'status' => 'draft',
        ]);
    }

    private function evidence(string $result = 'passed'): array
    {
        return [
            'model_name' => 'Tested AI Model',
            'model_version' => '2026.11',
            'test_scenario' => 'A fictional local education business requesting a realistic content schedule.',
            'findings' => 'Actual model output followed all required headings; I verified the facts and revised one example before accepting.',
            'result' => $result,
            'verified_execution' => '1',
            'check_accuracy' => '1',
            'check_structure' => '1',
            'check_safety' => '1',
            'check_reuse' => '1',
        ];
    }

    public function test_reviewer_queue_is_private_with_search_and_pagination(): void
    {
        $admin = $this->person('admin');
        $creator = $this->person('creator');
        $other = $this->person('creator');
        $this->prompt($creator);
        for ($i = 0; $i < 12; $i++) {
            $this->prompt($other, 'starter-2026-additional-prompt-'.$i);
        }

        $this->get(route('dashboard.prompt-quality'))->assertRedirect(route('login'));
        $this->get(route('account.prompt-quality'))->assertRedirect(route('login'));
        $this->actingAs($creator)->get(route('dashboard.prompt-quality'))->assertForbidden();
        $this->get(route('account.prompt-quality'))->assertOk()
            ->assertSee('Prompt Quality Center')
            ->assertSee('30-Day Social Media Content Planner')
            ->assertDontSee('starter-2026-additional-prompt', false);

        $this->actingAs($admin)->get(route('dashboard.prompt-quality'))->assertOk()
            ->assertSee('Showing 1–10 of 13 records')
            ->assertSee('Never tested');
        $this->get(route('dashboard.prompt-quality', ['page' => 2]))->assertOk()
            ->assertSee('Showing 11–13 of 13 records');
        $this->get(route('dashboard.prompt-quality', ['q' => 'no matching entry']))
            ->assertOk()->assertSee('No matching starter products');
        $this->get(route('dashboard.prompt-quality', ['status' => 'invalid']))
            ->assertSessionHasErrors('status');
    }

    public function test_only_owner_or_admin_can_view_or_record_private_model_tests(): void
    {
        $owner = $this->person('creator');
        $other = $this->person('creator');
        $admin = $this->person('admin');
        $member = $this->person('customer');
        $prompt = $this->prompt($owner);
        $show = route('account.prompts.quality', $prompt);
        $post = route('account.prompts.quality.store', $prompt);

        $this->get($show)->assertRedirect(route('login'));
        $this->post($post, $this->evidence())->assertRedirect(route('login'));

        $this->actingAs($member)->get($show)->assertForbidden();
        $this->post($post, $this->evidence())->assertForbidden();
        $this->actingAs($other)->get($show)->assertForbidden();
        $this->post($post, $this->evidence())->assertForbidden();

        $this->actingAs($owner)->get($show)->assertOk()
            ->assertSee('Suggested test scenario')->assertSee('No test evidence recorded yet');
        $this->post($post, $this->evidence())->assertForbidden();

        $this->actingAs($admin)->get($show)->assertOk()
            ->assertSee('Record a model test')->assertSee('Passed (all four checks required)');
        $this->assertDatabaseCount('prompt_quality_reviews', 0);
    }

    public function test_validation_and_attestation_block_unverified_pass_records(): void
    {
        $admin = $this->person('admin');
        $prompt = $this->prompt($admin);
        $this->actingAs($admin);

        $missing = $this->evidence();
        unset($missing['verified_execution']);
        $this->post(route('account.prompts.quality.store', $prompt), $missing)
            ->assertSessionHasErrors('verified_execution');

        $incorrect = $this->evidence();
        unset($incorrect['check_safety']);
        $this->post(route('account.prompts.quality.store', $prompt), $incorrect)
            ->assertSessionHasErrors('result');

        $short = $this->evidence();
        $short['findings'] = 'Too short';
        $this->post(route('account.prompts.quality.store', $prompt), $short)
            ->assertSessionHasErrors('findings');

        $this->assertDatabaseCount('prompt_quality_reviews', 0);
        $this->assertSame('draft', $prompt->fresh()->status);
    }

    public function test_recording_genuine_manual_evidence_does_not_publish_or_submit(): void
    {
        $admin = $this->person('admin');
        $prompt = $this->prompt($admin);
        $url = route('account.prompts.quality.store', $prompt);

        $this->actingAs($admin)->post($url, $this->evidence())
            ->assertRedirect(route('account.prompts.quality', $prompt))
            ->assertSessionHas('success');
        $this->assertDatabaseCount('prompt_quality_reviews', 1);
        $review = PromptQualityReview::firstOrFail();
        $this->assertSame('passed', $review->result);
        $this->assertSame(PromptQualityGate::fingerprint($prompt), $review->content_sha256);
        $this->assertSame('draft', $prompt->fresh()->status);
        $this->assertNull($prompt->fresh()->published_at);
        $this->assertTrue(PromptQualityGate::passes($prompt->fresh()));
        $this->get(route('account.prompts.quality', $prompt))->assertOk()
            ->assertSee('Passed human QA')->assertSee('Tested AI Model');
        $this->get(route('prompts.index'))->assertOk()
            ->assertDontSee('30-Day Social Media Content Planner');
        $this->assertDatabaseHas('audit_events', [
            'user_id' => $admin->id, 'action' => 'prompt.quality.record',
        ]);
    }

    public function test_starter_submission_and_approval_are_blocked_until_current_admin_qa_passes(): void
    {
        $admin = $this->person('admin');
        $prompt = $this->prompt($admin);
        $submit = route('account.prompts.submit', $prompt);
        $moderate = route('dashboard.prompts.moderate', $prompt);
        $this->actingAs($admin);

        $this->post($submit)->assertStatus(422);
        $this->assertSame('draft', $prompt->fresh()->status);
        $this->post(route('account.prompts.quality.store', $prompt), $this->evidence('needs_revision'))
            ->assertRedirect();
        $this->post($submit)->assertStatus(422);

        $this->post(route('account.prompts.quality.store', $prompt), $this->evidence())
            ->assertRedirect();
        $this->post($submit)->assertRedirect();
        $this->assertSame('pending', $prompt->fresh()->status);
        $this->post($moderate, ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('published', $prompt->fresh()->status);
        $this->assertNotNull($prompt->fresh()->published_at);
        $this->assertSame(2, PromptQualityReview::count());
        $this->assertSame(['submitted', 'approved'],
            $prompt->reviewEvents()->orderBy('id')->pluck('action')->all());
    }

    public function test_editing_substantive_prompt_content_invalidates_qa_and_forces_retest(): void
    {
        $admin = $this->person('admin');
        $prompt = $this->prompt($admin);
        $this->actingAs($admin);

        $this->post(route('account.prompts.quality.store', $prompt), $this->evidence())
            ->assertRedirect();
        $this->assertTrue(PromptQualityGate::passes($prompt->fresh()));

        $prompt->update(['content' => 'Revised private content that differs materially from the prior test.']);
        $this->assertFalse(PromptQualityGate::passes($prompt->fresh()));
        $this->get(route('account.prompts.quality', $prompt))->assertOk()
            ->assertSee('Revision needs retest')->assertSee('Older version');
        $this->post(route('account.prompts.submit', $prompt))->assertStatus(422);
        $this->assertSame('draft', $prompt->fresh()->status);

        $this->post(route('account.prompts.quality.store', $prompt), $this->evidence())
            ->assertRedirect();
        $this->assertTrue(PromptQualityGate::passes($prompt->fresh()));
        $this->assertSame(2, PromptQualityReview::count());
        $this->post(route('account.prompts.submit', $prompt))->assertRedirect();
    }

    public function test_older_and_nonstarter_prompts_keep_existing_moderation_behavior(): void
    {
        $creator = $this->person('creator');
        $admin = $this->person('admin');
        $legacy = $this->prompt($creator, 'existing-community-prompt');

        $this->assertFalse(PromptQualityGate::requiresReview($legacy));
        $this->actingAs($creator)->post(route('account.prompts.submit', $legacy))->assertRedirect();
        $this->assertSame('pending', $legacy->fresh()->status);
        $this->actingAs($admin)->post(route('dashboard.prompts.moderate', $legacy), [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertSame('published', $legacy->fresh()->status);
        $this->assertDatabaseCount('prompt_quality_reviews', 0);
    }

    public function test_pending_and_published_prompts_cannot_accept_new_qa_records(): void
    {
        $admin = $this->person('admin');
        $prompt = $this->prompt($admin);
        $this->actingAs($admin);
        $this->post(route('account.prompts.quality.store', $prompt), $this->evidence())->assertRedirect();
        $this->post(route('account.prompts.submit', $prompt))->assertRedirect();
        $this->post(route('account.prompts.quality.store', $prompt), $this->evidence())->assertStatus(422);
        $this->post(route('dashboard.prompts.moderate', $prompt), ['decision' => 'approve'])->assertRedirect();
        $this->post(route('account.prompts.quality.store', $prompt), $this->evidence())->assertStatus(422);
        $this->assertDatabaseCount('prompt_quality_reviews', 1);
    }

    public function test_editorial_scenario_is_source_backed_and_not_publicly_disclosed(): void
    {
        $creator = $this->person('creator');
        $prompt = $this->prompt($creator);
        $editorial = StarterPromptScenarios::forPrompt($prompt);
        $this->assertIsArray($editorial);
        $this->assertNotEmpty($editorial['scenario']);
        $this->assertGreaterThanOrEqual(3, count($editorial['checks']));

        $this->actingAs($creator)->get(route('account.prompts.quality', $prompt))
            ->assertOk()->assertSee($editorial['scenario']);
        $this->get(route('prompts.index'))->assertOk()->assertDontSee($editorial['scenario']);
        $this->assertNull(StarterPromptScenarios::forPrompt(
            $this->prompt($creator, 'legacy-editorial-test')
        ));
    }
}
