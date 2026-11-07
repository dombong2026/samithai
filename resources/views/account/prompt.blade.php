@extends('layouts.workspace')
@section('title',$prompt->title)
@section('breadcrumb','Account / Library')
@section('heading',$prompt->title)
@section('subtitle','Licensed prompt content')
@section('toolbar')<a href="{{ route('account.library') }}" class="btn">← Library</a>@endsection
@section('content')
<div class="panel stack">
<div class="flex wrap"><span class="tag">{{ $prompt->category->name }}</span><span class="tag">{{ ucfirst($prompt->license_type) }} license</span></div>
<p>{{ $prompt->summary }}</p>
<div class="flex between"><h2 style="margin:0">Prompt text</h2><button class="btn small" type="button" data-copy-target="prompt-text">Copy prompt</button></div>
<pre id="prompt-text" style="white-space:pre-wrap;overflow-wrap:anywhere;padding:20px;background:var(--soft);border-radius:12px;color:var(--ink);font:inherit">{{ $prompt->content }}</pre>
<div class="notice">Use this prompt only in accordance with the license supplied for this item. Copyright remains with its rights holder.</div>
</div>
@endsection
