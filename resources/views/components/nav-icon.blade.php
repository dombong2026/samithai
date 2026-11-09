@props(['name' => 'circle'])
<svg class="ui-symbol" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
@switch($name)
@case('dashboard')
<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>@break
@case('review')
<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5M9 11l2 2 4-4M9 17h6"/>@break
@case('categories')
<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>@break
@case('users')
<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6M17 15a5 5 0 0 1 4 5"/>@break
@case('posts')
<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/>@break
@case('pages')
<path d="M6 3h8l5 5v13H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M14 3v6h5M8 14h8M8 18h6"/>@break
@case('policies')
<path d="M12 2 4 6v6c0 5 3 8 8 10 5-2 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/>@break
@case('knowledge')
<path d="M12 6c-3-3-7-2-9-1v14c3-1 6-1 9 1 3-2 6-2 9-1V5c-3-1-6-2-9 1zM12 6v14"/>@break
@case('media')
<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="2"/><path d="m3 17 6-5 4 3 3-3 5 5"/>@break
@case('appearance')
<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18c2 0 3-1 3-3s1-2 2-2 4-1 4-4"/><circle cx="7.5" cy="10" r="1"/><circle cx="11" cy="7" r="1"/><circle cx="16" cy="9" r="1"/>@break
@case('footer')
<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 15h18M7 18h3M13 18h4"/>@break
@case('language')
<path d="M4 6h11M9 3v3M8 6c0 5-2 8-5 10M6 10c1 2 3 4 7 6M14 21l4-10 4 10M15.5 17h5"/>@break
@case('finance')
<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 16h4"/>@break
@case('legal')
<path d="M12 3v18M4 7h16M6 7l-3 6h6zM18 7l-3 6h6zM7 21h10"/>@break
@case('support')
<path d="M4 12a8 8 0 0 1 16 0v6a2 2 0 0 1-2 2h-2v-7h4M4 13h4v7H6a2 2 0 0 1-2-2zM12 20h3"/>@break
@case('security')
<path d="M12 2 4 6v6c0 5 3 8 8 10 5-2 8-5 8-10V6z"/><path d="M9 12h6M12 9v6"/>@break
@case('system')
<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>@break
@case('store')
<path d="M3 10 5 4h14l2 6v3a3 3 0 0 1-5 2 3 3 0 0 1-4 0 3 3 0 0 1-4 0 3 3 0 0 1-5-2v-3zM5 16v5h14v-5"/>@break
@case('account')
<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>@break
@case('library')
<path d="M5 3h14v18H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM9 7h6M9 11h6M9 15h4"/>@break
@case('saved')
<path d="M5 3h14v18l-7-5-7 5z"/>@break
@case('cart')
<path d="M3 4h2l2 12h12l2-9H6M9 21h.01M18 21h.01"/>@break
@case('insights')
<path d="M4 20V12M10 20V7M16 20V4M22 20H2"/>@break
@case('clock')
<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
@case('arrow-up-right')
<path d="M7 17 17 7M8 7h9v9"/>@break
@case('chevron')
<path d="m7 10 5 5 5-5"/>@break
@case('menu')
<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('check')
<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>@break
@case('spark')
<path d="m12 3 2.3 6.7L21 12l-6.7 2.3L12 21l-2.3-6.7L3 12l6.7-2.3z"/>@break
@default
<circle cx="12" cy="12" r="8"/>
@endswitch
</svg>
