@extends('layouts.workspace')
@section('title','My Tasks')
@section('breadcrumb', auth()->user()->isAdmin() ? 'Administration / My Tasks' : 'Account / My Tasks')
@section('heading','My Tasks')
@section('subtitle','Your private task workspace. Organize work without sharing tasks across accounts.')
@section('toolbar')
<button class="btn primary" type="button" data-modal-open="workspace-task-modal"><x-nav-icon name="plus"/> New task</button>
@endsection
@section('content')
<div class="workspace-task-manager">
<div class="workspace-task-stats">
<div class="workspace-task-stat"><span>Total tasks</span><strong>{{ number_format($totalCount) }}</strong></div>
<div class="workspace-task-stat"><span>Still open</span><strong>{{ number_format($openCount) }}</strong></div>
<div class="workspace-task-stat"><span>Overdue</span><strong>{{ number_format($overdueCount) }}</strong></div>
</div>
<form class="filter-bar workspace-task-filters" method="get" action="{{ route('workspace.tasks') }}">
<label class="sr-only" for="workspace-task-q">Search my tasks</label><input id="workspace-task-q" name="q" placeholder="Search task title or details" maxlength="100" value="{{ request('q') }}">
<label class="sr-only" for="workspace-task-status">Status</label><select id="workspace-task-status" name="status">
<option value="">All statuses</option>
@foreach(['todo' => 'To do','in_progress'=>'In progress','done'=>'Completed'] as $value=>$label)
<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
@endforeach
</select>
<label class="sr-only" for="workspace-task-priority">Priority</label><select name="priority" id="workspace-task-priority">
<option value="">All priorities</option>
@foreach(['high'=>'High','normal'=>'Normal','low'=>'Low'] as $value=>$label)
<option value="{{ $value }}" @selected(request('priority')===$value)>{{ $label }}</option>
@endforeach
</select>
<button class="btn primary" type="submit"><x-nav-icon name="search"/> Apply</button><a class="btn" href="{{ route('workspace.tasks') }}">Clear</a>
</form>
<div class="table-scroll workspace-task-table"><table>
<thead><tr><th>No.</th><th>Task</th><th>Priority</th><th>Status</th><th>Due date</th><th>Updated</th><th>Action</th></tr></thead>
<tbody>
@forelse($tasks as $task)
<tr>
<td>{{ $tasks->firstItem()+$loop->index }}</td>
<td><strong class="workspace-task-title">{{ $task->title }}</strong>
@if($task->description)<div class="muted tiny workspace-task-summary">{{ \Illuminate\Support\Str::limit($task->description, 100) }}</div>@endif</td>
<td><span class="workspace-task-chip priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span></td>
<td><span class="workspace-task-chip status-{{ $task->status }}">{{ $task->status === 'in_progress' ? 'In progress' : ($task->status === 'done' ? 'Completed' : 'To do') }}</span></td>
<td>@if($task->due_on)<time datetime="{{ $task->due_on->format('Y-m-d') }}" @class(['workspace-task-overdue' => $task->is_overdue])>{{ $task->due_on->format('d M Y') }}</time>@if($task->is_overdue)<span class="workspace-task-overdue-label">Overdue</span>@endif
@else<span class="muted">—</span>@endif</td>
<td>{{ $task->updated_at->format('d M Y') }}</td>
<td><button type="button" class="icon-btn" data-action-toggle="workspace-task-actions-{{ $task->id }}" aria-label="Actions for {{ $task->title }}" aria-haspopup="menu"><x-nav-icon name="more-vertical"/></button>
<template id="workspace-task-actions-{{ $task->id }}">
<button type="button" data-modal-open="workspace-task-modal" data-resource-url="{{ route('workspace.tasks.update',$task) }}" data-field-title="{{ $task->title }}" data-field-description="{{ $task->description }}" data-field-status="{{ $task->status }}" data-field-priority="{{ $task->priority }}" data-field-due-on="{{ $task->due_on?->format('Y-m-d') }}"><x-nav-icon name="edit"/> Edit task</button>
<form method="post" action="{{ route('workspace.tasks.destroy',$task) }}" data-confirm="Permanently delete this personal task?">@csrf @method('DELETE')<button type="submit" class="danger"><x-nav-icon name="trash"/> Delete task</button></form>
</template></td>
</tr>
@empty
<tr><td colspan="7" class="empty"><strong>No matching tasks.</strong> Create a task or clear your filters to see your work.</td></tr>
@endforelse
</tbody></table></div>
@include('partials.pagination',['paginator'=>$tasks])
<dialog id="workspace-task-modal" class="workspace-task-modal">
<div class="modal-head"><h2><span data-modal-mode>Create</span> task</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close task editor"><x-nav-icon name="x"/></button></div>
<form method="post" action="{{ route('workspace.tasks.store') }}" data-resource-form data-create-url="{{ route('workspace.tasks.store') }}">
@csrf<input type="hidden" name="_method" value="PUT" disabled>
<div class="modal-content stack">
<div class="field-group"><label for="task-title">Task title</label><input class="field" id="task-title" name="title" data-fill="title" required maxlength="160" placeholder="Describe the work to complete"></div>
<div class="field-group"><label for="task-description">Details (optional)</label><textarea id="task-description" class="field" name="description" data-fill="description" rows="4" maxlength="2000" placeholder="Context, notes or next steps"></textarea></div>
<div class="form-grid">
<div class="field-group"><label for="task-status">Status</label><select id="task-status" name="status" class="field" data-fill="status" required>
<option value="todo">To do</option><option value="in_progress">In progress</option><option value="done">Completed</option>
</select></div>
<div class="field-group"><label for="task-priority">Priority</label><select id="task-priority" name="priority" class="field" data-fill="priority" required>
<option value="normal">Normal</option><option value="high">High</option><option value="low">Low</option>
</select></div>
</div>
<div class="field-group"><label for="task-due-on">Due date (optional)</label><input id="task-due-on" name="due_on" data-fill="dueOn" class="field" type="date"></div>
<div class="muted tiny">Only you can see, edit, or delete your personal tasks. Your existing marketplace records are not affected.</div>
</div>
<div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn primary" type="submit">Save task</button></div>
</form></dialog>
</div>
@endsection
