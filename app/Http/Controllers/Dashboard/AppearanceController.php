<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\ContentEntry;
use App\Support\FrontendPageCopy;
use App\Support\FrontendPageAvailability;
use App\Models\SiteSetting;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppearanceController extends Controller
{
    private const DEFAULTS = [
        'site_name' => 'Samith AI',
        'site_description' => 'Discover and license practical AI prompts.',
        'hero_title' => 'Find the right prompt. Make every idea count.',
        'hero_subtitle' => 'Explore practical AI prompts from creators across different fields.',
        'primary_color' => '#ED702B',
        'secondary_color' => '#246BD0',
        'logo_media_id' => '',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(),403);
        $settings = [];
        foreach (self::DEFAULTS as $key => $default) $settings[$key] = SiteSetting::text($key,$default);


        $filters = $request->validate(['page_q' => ['nullable', 'string', 'max:80']]);
        $q = trim($filters['page_q'] ?? '');
        $builtins = [];
        foreach (FrontendPageCopy::definitions() as $key => $definition) {
            if ($q !== '' && !str_contains(mb_strtolower($definition['name'].' '.$definition['path']), mb_strtolower($q))) {
                continue;
            }
            $builtins[$key] = [
                ...$definition,
                'values' => FrontendPageCopy::values($key),
                'customized' => FrontendPageCopy::hasCustomCopy($key),
                'enabled' => FrontendPageAvailability::enabled($key),
                'toggleable' => FrontendPageAvailability::toggleable($key),
            ];
        }
        $managed = [
            ['key' => 'blog_detail', 'name' => 'Blog article detail', 'path' => '/blog/{slug}', 'manager' => 'posts', 'label' => 'Post Manager'],
            ['key' => 'policy_detail', 'name' => 'Published policy detail', 'path' => '/policies/{slug}', 'manager' => 'policies', 'label' => 'Policy Center'],
            ['key' => 'support_detail', 'name' => 'Support article detail', 'path' => '/support-center/articles/{slug}', 'manager' => 'knowledge', 'label' => 'Knowledge Base'],
        ];
        $managed = array_map(fn (array $entry) => [
            ...$entry,
            'enabled' => FrontendPageAvailability::enabled($entry['key']),
            'toggleable' => FrontendPageAvailability::toggleable($entry['key']),
        ], $managed);
        if ($q !== '') {
            $managed = array_values(array_filter($managed,
                fn ($entry) => str_contains(mb_strtolower($entry['name'].' '.$entry['path']), mb_strtolower($q))));
        }
        $customPages = ContentEntry::where('kind', 'page')
            ->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.$q.'%'))
            ->latest('id')->paginate(10, ['*'], 'custom_page')->withQueryString();

        return view('dashboard.appearance', [
            'settings' => $settings,
            'frontendPages' => $builtins,
            'managedPages' => $managed,
            'customPages' => $customPages,
            'pageSearch' => $q,
            'images' => MediaAsset::where('kind','image')->latest()->take(100)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(),403);
        $data = $request->validate([
            'site_name' => ['required','string','max:80'],
            'site_description' => ['required','string','max:300'],
            'hero_title' => ['required','string','max:180'],
            'hero_subtitle' => ['required','string','max:450'],
            'primary_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_media_id' => ['nullable','integer','exists:media_assets,id'],
        ]);
        if (filled($data['logo_media_id'] ?? null)) {
            abort_unless(MediaAsset::whereKey($data['logo_media_id'])->where('kind','image')->exists(),422);
        }
        foreach ($data as $key => $value) {
            SiteSetting::updateOrCreate(['key'=>$key],['value'=>(string) ($value ?? '')]);
        }
        if (! array_key_exists('logo_media_id',$data)) SiteSetting::updateOrCreate(['key'=>'logo_media_id'],['value'=>'']);
        AuditTrail::record($request,'appearance.update',null,['keys'=>array_keys($data)]);

        return back()->with('success','Site appearance saved.');
    }
}
