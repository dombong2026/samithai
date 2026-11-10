<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceTask;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceTasksController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['todo', 'in_progress', 'done'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
        ]);
        $query = WorkspaceTask::query()->ownedBy($request->user()->getKey());
        if (filled($data['q'] ?? null)) {
            $query->where(function ($builder) use ($data) {
                $builder->where('title', 'like', '%'.$data['q'].'%')
                    ->orWhere('description', 'like', '%'.$data['q'].'%');
            });
        }
        if (filled($data['status'] ?? null)) $query->where('status', $data['status']);
        if (filled($data['priority'] ?? null)) $query->where('priority', $data['priority']);

        return view('workspace.tasks', [
            'tasks' => $query->orderByRaw("CASE status WHEN 'todo' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
                ->orderByRaw('CASE WHEN due_on IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_on')->orderByDesc('id')->paginate(10)->withQueryString(),
            'totalCount' => WorkspaceTask::ownedBy($request->user()->getKey())->count(),
            'openCount' => WorkspaceTask::ownedBy($request->user()->getKey())->where('status', '!=', 'done')->count(),
            'overdueCount' => WorkspaceTask::ownedBy($request->user()->getKey())
                ->where('status', '!=', 'done')->whereDate('due_on', '<', today())->count(),
        ]);
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['todo', 'in_progress', 'done'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ]);
    }

    private function own(Request $request, WorkspaceTask $task): WorkspaceTask
    {
        abort_unless($task->user_id === $request->user()->getKey(), 403);

        return $task;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->data($request);
        $task = new WorkspaceTask($data);
        $task->user_id = $request->user()->getKey();
        $task->save();
        AuditTrail::record($request, 'workspace.task.create', $task);

        return redirect()->route('workspace.tasks')->with('success', 'Task created.');
    }

    public function update(Request $request, WorkspaceTask $task): RedirectResponse
    {
        $this->own($request, $task);
        $data = $this->data($request);
        $task->update($data);
        AuditTrail::record($request, 'workspace.task.update', $task);

        return redirect()->route('workspace.tasks')->with('success', 'Task updated.');
    }

    public function destroy(Request $request, WorkspaceTask $task): RedirectResponse
    {
        $this->own($request, $task);
        $task->delete();
        AuditTrail::record($request, 'workspace.task.delete', $task);

        return redirect()->route('workspace.tasks')->with('success', 'Task deleted.');
    }
}
