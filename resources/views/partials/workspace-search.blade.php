@php
// Navigation search is limited to real routes accessible to the current role.
// It is not a search for private prompt content or customer records.
$workspaceDestinations = $adminWorkspace ? [
    ['name'=>'Overview', 'url'=>route('dashboard'), 'icon'=>'dashboard', 'keywords'=>'admin dashboard home'],
    ['name'=>'Prompt moderation', 'url'=>route('dashboard.prompts'), 'icon'=>'review', 'keywords'=>'reviews marketplace'],
    ['name'=>'Categories', 'url'=>route('dashboard.categories'), 'icon'=>'categories', 'keywords'=>'marketplace category'],
    ['name'=>'Users & Roles', 'url'=>route('dashboard.users'), 'icon'=>'users', 'keywords'=>'user manager permission'],
    ['name'=>'Post Manager', 'url'=>route('dashboard.content','posts'), 'icon'=>'posts', 'keywords'=>'articles blog'],
    ['name'=>'Page Manager', 'url'=>route('dashboard.content','pages'), 'icon'=>'pages', 'keywords'=>'content custom pages'],
    ['name'=>'Policy Center', 'url'=>route('dashboard.content','policies'), 'icon'=>'policies', 'keywords'=>'legal content'],
    ['name'=>'Knowledge Base', 'url'=>route('dashboard.content','knowledge'), 'icon'=>'knowledge', 'keywords'=>'articles faq'],
    ['name'=>'Media Library', 'url'=>route('dashboard.media'), 'icon'=>'media', 'keywords'=>'images uploads video'],
    ['name'=>'Finance Manager', 'url'=>route('dashboard.finance'), 'icon'=>'finance', 'keywords'=>'money orders'],
    ['name'=>'License & Legal Review', 'url'=>route('dashboard.legal'), 'icon'=>'legal', 'keywords'=>'licenses legal'],
    ['name'=>'Support Center', 'url'=>route('dashboard.support'), 'icon'=>'support', 'keywords'=>'tickets help'],
    ['name'=>'Appearance', 'url'=>route('dashboard.appearance'), 'icon'=>'appearance', 'keywords'=>'frontend pages branding theme'],
    ['name'=>'Global Footer', 'url'=>route('dashboard.footer'), 'icon'=>'footer', 'keywords'=>'links'],
    ['name'=>'Language Manager', 'url'=>route('dashboard.languages'), 'icon'=>'language', 'keywords'=>'translations locale'],
    ['name'=>'Security Center', 'url'=>route('dashboard.security'), 'icon'=>'security', 'keywords'=>'security'],
    ['name'=>'System Center', 'url'=>route('dashboard.system'), 'icon'=>'system', 'keywords'=>'settings'],
] : [
    ['name'=>'Account overview', 'url'=>route('account'), 'icon'=>'dashboard', 'keywords'=>'home profile'],
    ['name'=>'My library', 'url'=>route('account.library'), 'icon'=>'library', 'keywords'=>'owned purchased prompts'],
    ['name'=>'Saved prompts', 'url'=>route('account.saved'), 'icon'=>'saved', 'keywords'=>'favorites bookmarks'],
    ['name'=>'My orders', 'url'=>route('account.orders'), 'icon'=>'finance', 'keywords'=>'purchases history'],
    ['name'=>'My cart', 'url'=>route('cart.index'), 'icon'=>'cart', 'keywords'=>'checkout shopping'],
    ['name'=>'Support tickets', 'url'=>route('account.support'), 'icon'=>'support', 'keywords'=>'help'],
    ['name'=>'Account settings', 'url'=>route('account.settings'), 'icon'=>'account', 'keywords'=>'profile password'],
];
if (!$adminWorkspace && $creatorWorkspace) {
    array_push($workspaceDestinations,
        ['name'=>'My prompts', 'url'=>route('account.prompts'), 'icon'=>'posts', 'keywords'=>'creator edit'],
        ['name'=>'My media', 'url'=>route('account.media'), 'icon'=>'media', 'keywords'=>'creator uploads'],
        ['name'=>'Creator insights', 'url'=>route('account.creator.insights'), 'icon'=>'insights', 'keywords'=>'analytics']
    );
}
if (\App\Support\FrontendPageAvailability::enabled('catalog')) {
    $workspaceDestinations[] = ['name'=>'Explore marketplace', 'url'=>route('prompts.index'), 'icon'=>'store', 'keywords'=>'browse prompts'];
}
@endphp
<div class="workspace-header-search" data-workspace-search>
  <label class="sr-only" for="workspace-search-input">Search workspace pages and tools</label>
  <x-nav-icon name="search"/>
  <input id="workspace-search-input" type="search" placeholder="Search pages and tools..." autocomplete="off"
    data-workspace-search-input aria-controls="workspace-search-results" aria-expanded="false">
  <kbd class="workspace-search-shortcut" aria-hidden="true">⌘ K</kbd>
  <nav class="workspace-search-results" id="workspace-search-results" aria-label="Workspace search results" data-workspace-search-results hidden>
    <span class="workspace-search-hint">Quick navigation</span>
    @foreach($workspaceDestinations as $destination)
    <a href="{{ $destination['url'] }}" data-workspace-search-item data-search-terms="{{ $destination['keywords'] }}">
      <x-nav-icon name="{{ $destination['icon'] }}"/> <span>{{ $destination['name'] }}</span>
    </a>
    @endforeach
    <p class="workspace-search-empty" data-workspace-search-empty hidden>No matching pages or tools.</p>
  </nav>
</div>
