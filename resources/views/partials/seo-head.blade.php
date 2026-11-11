@if(!empty($seo))
<meta name="description" content="{{ $seo['description'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta name="robots" content="{{ $seo['index'] ? 'index,follow' : 'noindex,nofollow' }}">
<meta property="og:site_name" content="{{ \App\Models\SiteSetting::text('site_name','Samith AI') }}">
<meta property="og:type" content="{{ $seo['og_type'] }}">
<meta property="og:title" content="{{ $seo['og_title'] }}">
<meta property="og:description" content="{{ $seo['og_description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo['og_title'] }}">
<meta name="twitter:description" content="{{ $seo['og_description'] }}">
@if($seo['image'])
<meta property="og:image" content="{{ $seo['image'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
@endif
@if($seo['schema'])
<script type="application/ld+json">{!! json_encode($seo['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endif
@endif
