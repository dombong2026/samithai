<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Support\AuditTrail;
use App\Support\SafeHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContentController extends Controller
{
    private const KINDS = ['pages' => 'page', 'posts' => 'post', 'policies' => 'policy', 'knowledge' => 'article'];

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    public function index(Request $request, string $section): View
    {
        $this->admin($request);
        abort_unless(isset(self::KINDS[$section]), 404);
        $data = $request->validate(['q' => ['nullable','string','max:100'], 'status' => ['nullable','in:draft,published']]);
        $query = ContentEntry::with(['author','media'])->where('kind', self::KINDS[$section]);
        if (filled($data['q'] ?? null)) $query->where('title','like','%'.$data['q'].'%');
        if (filled($data['status'] ?? null)) $query->where('status', $data['status']);

        return view('dashboard.content', [
            'section' => $section,
            'kind' => self::KINDS[$section],
            'entries' => $query->latest()->paginate(10)->withQueryString(),
            'mediaAssets' => MediaAsset::orderByDesc('id')->limit(100)->get(),
        ]);
    }

    private function validateContent(Request $request): array
    {
        return $request->validate([
            'title' => ['required','string','max:160'],
            'excerpt' => ['nullable','string','max:3000'],
            'body_html' => ['required','string','max:180000'],
            'featured_media_id' => ['nullable','integer','exists:media_assets,id'],
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $this->admin($request);
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $data = $this->validateContent($request);
        $slug = Str::slug($data['title']);
        if ($slug === '') return back()->withErrors(['title' => 'Use a title that produces a URL slug.']);
        if (ContentEntry::where('kind', $kind)->where('slug', $slug)->exists()) {
            return back()->withErrors(['title' => 'A '. $kind .' with this URL already exists.']);
        }
        $entry = ContentEntry::create([
            ...$data,
            'body_html' => SafeHtml::clean($data['body_html']),
            'slug' => $slug,
            'kind' => $kind,
            'user_id' => $request->user()->id,
            'status' => 'draft',
        ]);
        AuditTrail::record($request, 'content.create', $entry, ['kind'=>$kind]);

        return back()->with('success', 'Draft created.');
    }

    public function update(Request $request, ContentEntry $entry): RedirectResponse
    {
        $this->admin($request);
        $data = $this->validateContent($request);
        $entry->update([...$data, 'body_html' => SafeHtml::clean($data['body_html'])]);
        AuditTrail::record($request, 'content.update', $entry);

        return back()->with('success', 'Content updated.');
    }

    public function publish(Request $request, ContentEntry $entry): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['draft','published'])]]);
        $entry->update([
            'status' => $data['status'],
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);
        AuditTrail::record($request, 'content.status', $entry, ['status'=>$data['status']]);

        return back()->with('success', 'Publication status updated.');
    }

    public function destroy(Request $request, ContentEntry $entry): RedirectResponse
    {
        $this->admin($request);
        $entry->delete();
        AuditTrail::record($request, 'content.delete', $entry);

        return back()->with('success', 'Content deleted.');
    }

    public function preview(Request $request, ContentEntry $entry): View
    {
        $this->admin($request);
        return view('marketplace.content', [
            'entry' => $entry->load(['media','seo.ogMedia','author']),
            'preview' => true,
            'seo' => \App\Support\SeoHead::content($entry, null, true),
        ]);
    }
}
