<section class="workspace-task-overview" aria-labelledby="{{ $taskSectionId }}">
<div class="workspace-task-overview-top">
<div><div class="eyebrow">PERSONAL WORKSPACE</div><h2 id="{{ $taskSectionId }}">My tasks</h2><p>Your private priorities, independent of marketplace moderation.</p></div>
<a class="btn small" href="{{ route('workspace.tasks') }}"><x-nav-icon name="list-todo"/> Manage tasks</a>
</div>
<div class="workspace-task-overview-stats">
<div><span>Open tasks</span><strong>{{ number_format($personalTaskCount) }}</strong></div>
<div><span>Overdue</span><strong>{{ number_format($personalTaskOverdue) }}</strong></div>
</div>
@if($personalNextTasks->isEmpty())
<p class="workspace-task-overview-empty">No open tasks yet. Create one to plan your next action.</p>
@else
<ul class="workspace-task-overview-list">
@foreach($personalNextTasks as $task)
<li><a href="{{ route('workspace.tasks') }}"><span class="workspace-task-dot priority-{{ $task->priority }}"></span><span>{{ \Illuminate\Support\Str::limit($task->title, 72) }}</span>@if($task->due_on)<time datetime="{{ $task->due_on->format('Y-m-d') }}" @class(['workspace-task-overdue' => $task->is_overdue])>{{ $task->due_on->format('d M') }}</time>@endif</a></li>
@endforeach
</ul>
@endif
</section>
