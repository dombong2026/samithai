<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Prompt;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WorkspacePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceBoostTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    public function test_shortcuts_require_login_and_are_role_scoped(): void
    {
        $this->put(route('workspace.shortcuts.update', 'account'), [
            'shortcuts' => ['library'],
        ])->assertRedirect(route('login'));

        $customer = $this->person('customer');
        $this->actingAs($customer)->put(route('workspace.shortcuts.update', 'admin'), [
            'shortcuts' => ['users'],
        ])->assertForbidden();
        $this->delete(route('workspace.shortcuts.reset', 'admin'))->assertForbidden();
        $this->put(route('workspace.shortcuts.update', 'account'), [
            'shortcuts' => ['moderation'],
        ])->assertSessionHasErrors('shortcuts.0');
        $this->put(route('workspace.shortcuts.update', 'account'), [
            'shortcuts' => ['https://example.invalid'],
        ])->assertSessionHasErrors('shortcuts.0');
        $this->put(route('workspace.shortcuts.update', 'account'), [
            'shortcuts' => ['library', 'library'],
        ])->assertSessionHasErrors('shortcuts.1');
        $this->put(route('workspace.shortcuts.update', 'account'), [
            'shortcuts' => ['library', 'saved', 'orders', 'cart', 'tickets', 'settings', 'marketplace'],
        ])->assertSessionHasErrors('shortcuts');
        $this->assertDatabaseCount('workspace_preferences', 0);
    }

    public function test_shortcuts_persist_per_user_and_reset_to_role_defaults(): void
    {
        $member = $this->person('customer');
        $other = $this->person('customer');

        $this->actingAs($member)->put(route('workspace.shortcuts.update', 'account'), [
            'shortcuts' => ['orders', 'cart'],
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('workspace_preferences', [
            'user_id' => $member->id, 'context' => 'account',
        ]);
        $this->assertSame(['orders', 'cart'], WorkspacePreference::firstOrFail()->shortcuts);
        $this->get(route('account'))->assertOk()
            ->assertSee('data-launch-key="orders"', false)
            ->assertSee('data-launch-key="cart"', false)
            ->assertDontSee('data-launch-key="library"', false);

        $this->actingAs($other)->get(route('account'))->assertOk()
            ->assertSee('data-launch-key="library"', false)
            ->assertDontSee('data-launch-key="cart"', false);

        $this->actingAs($member)->delete(route('workspace.shortcuts.reset', 'account'))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('workspace_preferences', 0);
        $this->get(route('account'))->assertOk()->assertSee('data-launch-key="library"', false);
        $this->assertDatabaseHas('audit_events', [
            'user_id' => $member->id, 'action' => 'workspace.shortcuts.reset',
        ]);
    }

    public function test_creators_receive_only_permitted_creation_tools(): void
    {
        $creator = $this->person('creator');
        $html = $this->actingAs($creator)->get(route('account'))->assertOk()
            ->assertSee('Account action center')
            ->assertSee('Quick launch')
            ->assertSee('data-launch-key="prompts"', false)
            ->getContent();

        $this->assertStringContainsString(route('account.creator.insights'), $html);
        $this->assertStringNotContainsString('data-launch-key="moderation"', $html);

        $this->put(route('workspace.shortcuts.update', 'account'), [
            'shortcuts' => ['prompts', 'insights', 'media'],
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(['prompts', 'insights', 'media'], WorkspacePreference::firstOrFail()->shortcuts);
    }

    public function test_admin_has_real_weekly_totals_and_no_private_prompt_body(): void
    {
        $admin = $this->person('admin');
        $customer = $this->person('customer');
        SupportTicket::create(['user_id' => $customer->id, 'subject' => 'Weekly support request', 'status' => 'open']);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Weekly activity pulse')->assertSee('New accounts')
            ->assertSee('Support requests')->assertSee('Quick launch')
            ->assertSee('data-launch-key="moderation"', false);
        $this->actingAs($customer)->get(route('dashboard'))->assertForbidden();

        $this->actingAs($admin)->put(route('workspace.shortcuts.update', 'admin'), [
            'shortcuts' => ['security', 'media'],
        ])->assertRedirect();
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('data-launch-key="security"', false)
            ->assertDontSee('data-launch-key="moderation"', false);
        $this->get(route('account'))->assertOk()
            ->assertDontSee('data-launch-key="security"', false);
    }

    public function test_activity_feed_is_scoped_and_hides_raw_audit_details(): void
    {
        $member = $this->person('customer');
        $other = $this->person('customer');
        AuditEvent::create([
            'user_id' => $member->id, 'action' => 'account.profile.update',
            'details' => ['private' => 'DO_NOT_EXPOSE_AUDIT_DETAILS'],
            'ip_hash' => str_repeat('a', 64), 'created_at' => now(),
        ]);
        AuditEvent::create([
            'user_id' => $other->id, 'action' => 'account.password.update',
            'details' => ['private' => 'ANOTHER_USER_PRIVATE_AUDIT'],
            'created_at' => now(),
        ]);
        $this->actingAs($member)->get(route('account'))->assertOk()
            ->assertSee('Updated account details')
            ->assertDontSee('Changed account password')
            ->assertDontSee('DO_NOT_EXPOSE_AUDIT_DETAILS')
            ->assertDontSee('ANOTHER_USER_PRIVATE_AUDIT')
            ->assertDontSee(str_repeat('a', 64));
        $this->actingAs($other)->get(route('account'))->assertOk()
            ->assertSee('Changed account password')->assertDontSee('Updated account details');
    }
}
