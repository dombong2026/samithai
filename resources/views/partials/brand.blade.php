{{-- Keeps administrator-uploaded custom logos working; defaults to Samith AI wordmark. --}}
<a class="brand brand-with-logo" href="{{ $href ?? route('home') }}" aria-label="{{ \App\Models\SiteSetting::text('site_name','Samith AI') }}">
@if($siteLogo = \App\Models\SiteSetting::text('logo_media_id'))
  <img class="brand-mark" src="{{ route('media.serve', $siteLogo) }}" alt="" style="object-fit:cover" width="36" height="36">
  <span>{{ \App\Models\SiteSetting::text('site_name','Samith AI') }}</span>
@else
  <img class="brand-logo brand-logo-light" src="{{ asset('branding/samith-ai-logo-light.svg') }}" alt="" width="143" height="35" decoding="async">
  <img class="brand-logo brand-logo-dark" src="{{ asset('branding/samith-ai-logo-dark.svg') }}" alt="" width="143" height="35" decoding="async" aria-hidden="true">
@endif
</a>
