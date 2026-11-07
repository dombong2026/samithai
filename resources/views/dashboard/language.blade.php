@extends('layouts.workspace')
@section('title','Language Manager')
@section('breadcrumb','Administration / Languages')
@section('heading','Language Manager')
@section('subtitle','Regional language URLs and session-based preferences.')
@section('toolbar')<a class="btn primary" href="{{ route('locale.home',['locale'=>$selected]) }}">Preview current language URL</a>@endsection
@section('content')
<div class="notice warning">All 13 requested language codes are routable, but only the initial shared-interface strings are translated. Public content, product descriptions, policy wording and complete native-language review remain required before claiming full localization. Visitor country is advisory and disabled by default unless a trusted Cloudflare proxy is configured.</div>
<div class="table-scroll" style="margin-top:22px"><table><thead><tr><th>No.</th><th>Locale</th><th>Language</th><th>URL</th><th>Preview</th></tr></thead><tbody>
@foreach($locales as $code => $name)
<tr><td>{{ $loop->iteration }}</td><td class="title-cell">{{ $code }}</td><td>{{ $name }}</td>
<td>{{ url('/'.$code) }}</td>
<td><a class="btn small" href="{{ route('locale.home',['locale'=>$code]) }}" target="_blank" rel="noopener">Open locale →</a></td></tr>
@endforeach
</tbody></table></div>
@endsection
