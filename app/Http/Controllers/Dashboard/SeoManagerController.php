<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\Prompt;
use App\Support\AuditTrail;
use App\Support\EditorialGate;
use App\Support\SeoHead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SeoManagerController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function resolve(string $kind, int $id): ContentEntry|Prompt
    {
        return match ($kind) {
            'post', 'page' => ContentEntry::query()->where('kind', $kind)->findOrFail($id),
            'prompt' => Prompt::query()->findOrFail($id),
            default => abort(404),
        };
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'kind' => ['nullable',Rule::in(['post','page','prompt'])],
            'q' => ['nullable','string','max:100'],
        ]);
        $kind = $data['kind'] ?? 'post';
        $query = $kind === 'prompt'
            ? Prompt::query()->with('seo')
            : ContentEntry::query()->where('kind', $kind)->with('seo');

        if (filled($data['q'] ?? null)) {
            $query->where('title','like','%'.$data['q'].'%');
        }
        return view('dashboard.seo.index', [
            'kind' => $kind,
            'items' => $query->latest('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function edit(Request $request, string $kind, int $id): View
    {
        $this->authorizeAdmin($request);
        $item = $this->resolve($kind, $id)->load($kind === 'prompt'
            ? ['seo.ogMedia','featuredMedia'] : ['seo.ogMedia','media']);
        $seo = $item instanceof Prompt ? SeoHead::prompt($item) :
            SeoHead::content($item, null, true);
        return view('dashboard.seo.edit', [
            'kind' => $kind,
            'item' => $item,
            'seo' => $seo,
            'assets' => MediaAsset::query()->where('kind','image')->latest('id')->limit(100)->get(),
        ]);
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $item = $this->resolve($kind, $id);
        $data = $request->validate([
            'seo_title' => ['nullable','string','max:160'],
            'meta_description' => ['nullable','string','max:300'],
            'canonical_path' => ['nullable','string','max:500',
                'regex:~^/(?:pages|blog|prompts)/[a-z0-9]+(?:-[a-z0-9]+)*$~D'],
            'og_title' => ['nullable','string','max:160'],
            'og_description' => ['nullable','string','max:300'],
            'og_media_id' => ['nullable','integer',Rule::exists('media_assets','id')->where('kind','image')],
            'robots_index' => ['required','boolean'],
            'structured_data_enabled' => ['sometimes','boolean'],
        ]);
        // Canonical may only point inside the same type's public URL family.
        $expected = $kind === 'post' ? '/blog/' :
            ($kind === 'page' ? '/pages/' : '/prompts/');
        if (filled($data['canonical_path'] ?? null) &&
            !str_starts_with($data['canonical_path'], $expected)) {
            return back()->withErrors(['canonical_path' => 'Use an internal URL from the correct content section.']);
        }

        $data = array_map(static fn ($v) => is_string($v) && trim($v) === '' ? null : $v, $data);

        // Published articles must not expose unreviewed images via OG metadata.
        // Draft approvals and schedules are also invalidated when the social
        // image changes, just as they are for changes to the featured image.
        $socialImageChanged = array_key_exists('og_media_id', $data)
            && (int) ($item->seo?->og_media_id ?? 0) !== (int) ($data['og_media_id'] ?? 0);
        if ($socialImageChanged && $item instanceof ContentEntry
            && $item->status === 'published') {
            return back()->withErrors([
                'og_media_id' => 'Unpublish this article or page and obtain fresh human review before changing its public social image.',
            ]);
        }

        $approvalInvalidated = $socialImageChanged && $item instanceof ContentEntry
            && $item->status === 'draft' && EditorialGate::requiresReview($item);
        $item->seo()->updateOrCreate([], $data);
        if ($approvalInvalidated) {
            EditorialGate::invalidate($item);
            EditorialGate::revision($item, $request->user()->id, 'seo_image_changed');
        }
        AuditTrail::record($request, 'seo.update', $item, [
            'kind' => $kind,
            'social_image_changed' => $socialImageChanged,
            'approval_invalidated' => $approvalInvalidated,
        ]);

        return back()->with('success', 'SEO metadata saved. Review the public preview before sharing.');
    }
}
