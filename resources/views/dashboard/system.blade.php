@extends('layouts.workspace')
@section('title','System Center')
@section('breadcrumb','Administration / System')
@section('heading','System Center')
@section('subtitle','Live hosting requirements and application health.')
@section('toolbar')<a class="btn primary" href="{{ route('dashboard.system') }}">Run checks again</a>@endsection
@section('content')
<div class="form-grid">
<div class="panel"><h2>Requirement checks</h2>
@foreach($requirements as $name => $ok)
<div class="flex between" style="padding:10px 0;border-bottom:1px solid var(--line)"><span>{{ $name }}</span><strong class="{{ $ok ? 'status-pass' : 'status-fail' }}">{{ $ok ? 'PASS' : 'ACTION NEEDED' }}</strong></div>
@endforeach
</div>
<div class="panel"><h2>Runtime information</h2>
@foreach($runtime as $name => $value)
<div class="flex between" style="padding:10px 0;border-bottom:1px solid var(--line)"><span>{{ $name }}</span><strong>{{ $value }}</strong></div>
@endforeach
<div class="notice warning" style="margin-top:20px">These live checks do not replace backup restoration, external SMTP, real payment provider, cPanel/aaPanel or physical-device acceptance.</div></div>
</div>
@endsection
