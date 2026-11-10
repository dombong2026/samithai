<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkspaceTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class WorkspaceTasksTest extends TestCase
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

    private function form(string $title = 'Prepare weekly summary'): array
    {
        return [
            'title' => $title,
            'description' => 'Review outstanding work before the team meeting.',
            'status' => 'todo', 'priority' => 'normal',
            'due_on' => now()->addDays(3)->format('Y-m-d'),
        ];
    }

    public function test_guests_cannot_view_or_mutate_tasks(): void
    {
        $this->get(route('workspace.tasks'))->assertRedirect(route('login'));
        $this->post(route('workspace.tasks.store'), $this->form())->assertRedirect(route('login'));
        $this->put(route('workspace.tasks.update', 999), $this->form())->assertRedirect(route('login'));
        $this->delete(route('workspace.tasks.destroy', 999))->assertRedirect(route('login'));
        $this->assertDatabaseCount('workspace_tasks', 0);
    }

    public function test_admin_creator_and_member_have_complete_own_task_crud(): void
    {
        foreach (['admin', 'creator', 'customer'] as $role) {
            $user = $this->person($role);
            $this->actingAs($user)->get(route('workspace.tasks'))->assertOk()
                ->assertSee('My Tasks')->assertSee('New task');
            $this->post(route('workspace.tasks.store'), $this->form('Task for '.$role))
                ->assertRedirect(route('workspace.tasks'))->assertSessionHas('success');
            $task = WorkspaceTask::where('user_id', $user->id)->firstOrFail();
            $this->assertSame('todo', $task->status);

            $this->get(route('workspace.tasks'))->assertOk()
                ->assertSee('Task for '.$role)->assertSee('data-action-toggle="workspace-task-actions-'.$task->id.'"', false);
            $updated = $this->form('Updated task '.$role);
            $updated['status'] = 'in_progress';
            $updated['priority'] = 'high';
            $this->put(route('workspace.tasks.update', $task), $updated)
                ->assertRedirect(route('workspace.tasks'))->assertSessionHas('success');
            $this->assertSame('high', $task->fresh()->priority);
            $this->assertSame('in_progress', $task->fresh()->status);
            $this->get(route('workspace.tasks'))->assertOk()->assertSee('Updated task '.$role);

            $this->delete(route('workspace.tasks.destroy', $task))
                ->assertRedirect(route('workspace.tasks'))->assertSessionHas('success');
            $this->assertDatabaseMissing('workspace_tasks', ['id' => $task->id]);
            $this->assertDatabaseHas('audit_events', [
                'user_id' => $user->id, 'action' => 'workspace.task.create',
            ]);
            $this->assertDatabaseHas('audit_events', [
                'user_id' => $user->id, 'action' => 'workspace.task.update',
            ]);
            $this->assertDatabaseHas('audit_events', [
                'user_id' => $user->id, 'action' => 'workspace.task.delete',
            ]);
        }
    }

    public function test_tasks_remain_private_even_from_another_user_or_an_admin(): void
    {
        $owner = $this->person('creator');
        $other = $this->person('customer');
        $admin = $this->person('admin');
        $this->actingAs($owner)->post(route('workspace.tasks.store'),
            $this->form('CONFIDENTIAL_TASK_ALPHA'))->assertRedirect();
        $task = WorkspaceTask::where('user_id', $owner->id)->firstOrFail();
        foreach ([$other, $admin] as $viewer) {
            $this->actingAs($viewer)->get(route('workspace.tasks'))->assertOk()
                ->assertDontSee('CONFIDENTIAL_TASK_ALPHA');
            $this->put(route('workspace.tasks.update', $task),
                $this->form('Tampered task'))->assertForbidden();
            $this->delete(route('workspace.tasks.destroy', $task))->assertForbidden();
            $this->get($viewer->isAdmin() ? route('dashboard') : route('account'))
                ->assertOk()->assertDontSee('CONFIDENTIAL_TASK_ALPHA');
        }
        $this->assertSame('CONFIDENTIAL_TASK_ALPHA', $task->fresh()->title);
        $this->actingAs($owner)->get(route('account'))->assertOk()
            ->assertSee('CONFIDENTIAL_TASK_ALPHA');
    }

    public function test_validation_rejects_invalid_fields_and_preserves_data(): void
    {
        $user = $this->person('customer');
        $this->actingAs($user);
        $this->post(route('workspace.tasks.store'), [
            'title' => '', 'description' => str_repeat('x', 2001),
            'status' => 'published', 'priority' => 'critical',
            'due_on' => 'tomorrow',
        ])->assertSessionHasErrors(['title', 'description', 'status', 'priority', 'due_on']);
        $this->assertDatabaseCount('workspace_tasks', 0);
        $this->post(route('workspace.tasks.store'), $this->form())->assertRedirect();
        $task = WorkspaceTask::firstOrFail();
        $this->put(route('workspace.tasks.update', $task), [
            ...$this->form('Bad revision'), 'status' => 'unknown',
        ])->assertSessionHasErrors('status');
        $this->assertSame('Prepare weekly summary', $task->fresh()->title);
    }

    public function test_search_filters_and_pagination_are_owner_scoped(): void
    {
        $user = $this->person('customer');
        $other = $this->person('creator');
        for ($i = 0; $i < 13; $i++) {
            $task = new WorkspaceTask([
                ...$this->form('Personal '.$i),
                'status' => $i % 2 ? 'done' : 'todo',
                'priority' => $i % 2 ? 'low' : 'high',
            ]);
            $task->user_id = $user->id;
            $task->save();
        }
        $foreign = new WorkspaceTask($this->form('FOREIGN_TASK_HIDDEN'));
        $foreign->user_id = $other->id;
        $foreign->save();

        $this->actingAs($user)->get(route('workspace.tasks'))->assertOk()
            ->assertSee('Showing 1–10 of 13 records')
            ->assertDontSee('FOREIGN_TASK_HIDDEN');
        $this->get(route('workspace.tasks', ['page' => 2]))->assertOk()
            ->assertSee('Showing 11–13 of 13 records');
        $this->get(route('workspace.tasks', [
            'q' => 'Personal 12', 'status' => 'todo', 'priority' => 'high',
        ]))->assertOk()->assertSee('Personal 12')->assertDontSee('Personal 11');
        $this->get(route('workspace.tasks', ['status' => 'done', 'priority' => 'high']))
            ->assertOk()->assertSee('No matching tasks.');
    }

    public function test_dashboard_summary_tracks_only_open_and_overdue_personal_tasks(): void
    {
        $owner = $this->person('admin');
        $this->actingAs($owner);
        $this->post(route('workspace.tasks.store'), [
            ...$this->form('Overdue personal reminder'),
            'due_on' => now()->subDays(2)->format('Y-m-d'), 'priority' => 'high',
        ])->assertRedirect();
        $this->post(route('workspace.tasks.store'), [
            ...$this->form('Completed personal reminder'), 'status' => 'done',
        ])->assertRedirect();

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Overdue personal reminder')
            ->assertDontSee('Completed personal reminder')
            ->assertSee('My tasks')
            ->assertSee('workspace-task-overview');
        $this->get(route('account'))->assertOk()
            ->assertSee('Overdue personal reminder');
        $this->get(route('workspace.tasks'))->assertOk()
            ->assertSee('Overdue')->assertSee('Completed personal reminder');
    }

    public function test_account_workspaces_link_to_tasks_from_sidebar_and_header_search(): void
    {
        foreach (['admin', 'creator', 'customer'] as $role) {
            $user = $this->person($role);
            $html = $this->actingAs($user)->get(
                $role === 'admin' ? route('dashboard') : route('account')
            )->assertOk()->getContent();
            $this->assertStringContainsString('My tasks', $html);
            $this->assertStringContainsString(route('workspace.tasks'), $html);
            $this->assertStringContainsString('data-workspace-search-item', $html);
        }
    }
}
