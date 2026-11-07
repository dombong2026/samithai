@extends('layouts.workspace')
@section('title','Content translations')
@section('breadcrumb','Administration / Translation review')
@section('heading','Translations: '.$entry->title)
@section('subtitle','Save each translation as a draft, then review its accuracy before publishing.')
@section('toolbar')<a class="btn" href="{{ route('dashboard.content', ['page'=>'pages','post'=>'posts','policy'=>'policies','article'=>'knowledge'][$entry->kind]) }}">← Content manager</a>@endsection
@section('content')
<div class="notice warning">Automatic language detection is not translation. Source content remains the fallback until a translated version is reviewed. Administrator approval records an internal review decision, not independent native-speaker certification or legal sign-off.</div>
<div class="panel" style="margin-top:20px"><h2>Source content</h2><p><strong>{{ $entry->title }}</strong> · {{ ucfirst($entry->status) }}</p><p class="muted">{{ $entry->excerpt }}</p></div>
<div class="stack" style="margin-top:20px">
@foreach($locales as $locale=>$name)
@php($translation=$entry->translations->firstWhere('locale',$locale))
<div class="panel stack"><div class="flex between wrap"><h2>{{ $name }} ({{ $locale }})</h2><span class="pill {{ $translation?->status ?? 'draft' }}">{{ ucfirst($translation?->status ?? 'Missing') }}</span></div>
<form method="post" action="{{ route('dashboard.translations.update',[$entry,$locale]) }}" class="stack">@csrf @method('PUT')
<div class="field-group"><label for="title-{{ $locale }}">Translated title</label><input class="field" id="title-{{ $locale }}" name="title" maxlength="160" required value="{{ $translation?->title }}"></div>
<div class="field-group"><label for="excerpt-{{ $locale }}">Translated summary</label><textarea class="field" id="excerpt-{{ $locale }}" name="excerpt" rows="2" maxlength="3000">{{ $translation?->excerpt }}</textarea></div>
<div class="field-group"><label for="body-{{ $locale }}">Translated body (approved safe HTML only)</label><textarea class="field" id="body-{{ $locale }}" name="body_html" rows="6" maxlength="180000" required>{{ $translation?->body_html }}</textarea></div>
<div><button class="btn primary" type="submit">Save draft</button></div></form>
@if($translation)
<div class="flex wrap"><form method="post" action="{{ route('dashboard.translations.review',[$entry,$locale]) }}" data-confirm="Confirm editorial review for {{ $name }}? This is not independent legal approval.">@csrf<input type="hidden" name="decision" value="approve"><button class="btn" type="submit" @disabled($entry->status!=='published')>Review & publish translation</button></form>
@if($translation->status==='published')<form method="post" action="{{ route('dashboard.translations.review',[$entry,$locale]) }}" data-confirm="Unpublish this translation?">@csrf<input type="hidden" name="decision" value="revoke"><button class="btn danger" type="submit">Unpublish</button></form>@endif
@if($translation->reviewed_at)<span class="muted tiny">Reviewed {{ $translation->reviewed_at->format('d M Y') }} by {{ $translation->reviewer?->name ?? 'Unknown' }}</span>@endif</div>
@endif
</div>
@endforeach
</div>
@endsection
