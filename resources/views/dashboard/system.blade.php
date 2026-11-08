@extends('layouts.workspace')
@section('title','System Center')
@section('breadcrumb','Administration / System')
@section('heading','System Center')
@section('subtitle','Read-only runtime readiness checks, with release limitations clearly identified.')
@section('toolbar')<a class="btn primary" href="{{ route('dashboard.system') }}">Run checks again</a>@endsection
@section('content')
<div class="notice" style="margin-bottom:20px">
    Runtime: {{ $summary['pass'] }} passed, {{ $summary['review'] }} require review, {{ $summary['fail'] }} critical failures.
    Passing checks do not approve customer hosting, payment processing, SMTP delivery, legal compliance or a production release.
</div>
<div class="form-grid">
<div class="panel"><h2>Infrastructure checks</h2>
@foreach($checks as $check)
<div style="padding:10px 0;border-bottom:1px solid var(--line)">
    <div class="flex between"><span>{{ $check['label'] }}</span>
        <strong class="{{ $check['status'] === 'pass' ? 'status-pass' : 'status-fail' }}">
            {{ $check['status'] === 'pass' ? 'PASS' : ($check['status'] === 'review' ? 'REVIEW' : 'ACTION NEEDED') }}
        </strong>
    </div>
    <small class="muted">{{ $check['detail'] }}</small>
</div>
@endforeach
</div>
<div class="panel"><h2>Non-sensitive runtime information</h2>
@foreach($runtime as $name => $value)
<div class="flex between" style="padding:10px 0;border-bottom:1px solid var(--line)"><span>{{ $name }}</span><strong>{{ $value }}</strong></div>
@endforeach
<div class="notice warning" style="margin-top:20px">Queue depth is read without accessing job payloads. Worker liveness, backup restoration and recovery acceptance require separate verification. No migrations, writes or external HTTP requests are performed by these checks. This report does not replace independent cPanel/aaPanel installation, backup restoration, real-device, SMTP or payment acceptance.</div>
</div>
</div>
@endsection
