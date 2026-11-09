<section class="workspace-activity-panel" aria-labelledby="{{ $activityHeadingId }}">
<div class="workspace-activity-header">
<div><div class="eyebrow">ACTIVITY</div><h2 id="{{ $activityHeadingId }}">{{ $activityTitle }}</h2><p class="muted">{{ $activityDescription }}</p></div>
</div>
@if($workspaceEvents->isEmpty())
<div class="workspace-activity-empty"><x-nav-icon name="clock"/> <span>No recorded activity yet. Your updates will appear here.</span></div>
@else
<div class="workspace-activity-list">
@foreach($workspaceEvents as $activity)
<div class="workspace-activity-row">
<span class="workspace-activity-indicator"><x-nav-icon name="check"/></span>
<strong>{{ $activity['label'] }}</strong>
<time datetime="{{ $activity['time']->toIso8601String() }}" title="{{ $activity['time']->format('d M Y, H:i') }}">{{ $activity['time']->diffForHumans() }}</time>
</div>
@endforeach
</div>
@endif
</section>
