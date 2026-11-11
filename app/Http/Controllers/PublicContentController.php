<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use Illuminate\View\View;

class PublicContentController extends Controller
{
    public function show(string $kind, string $slug): View
    {
        abort_unless(in_array($kind, ['page','post','policy','article'], true), 404);
        $entry = ContentEntry::with(['media','translations','seo.ogMedia','author'])->where('kind', $kind)
            ->where('slug', $slug)->where('status', 'published')->firstOrFail();

        $locale=app()->getLocale();
        $translated=$entry->translations->first(fn ($row) => $row->locale === $locale && $row->status === 'published');

        return view('marketplace.content', [
            'entry'=>$entry, 'preview'=>false,
            'seo'=>\App\Support\SeoHead::content($entry, [
                'title'=>$translated?->title ?? $entry->title,
                'excerpt'=>$translated?->excerpt ?? $entry->excerpt,
            ]),
            'display'=>[
                'title'=>$translated?->title ?? $entry->title,
                'excerpt'=>$translated?->excerpt ?? $entry->excerpt,
                'body_html'=>$translated?->body_html ?? $entry->body_html,
            ],
            'translationAvailable'=>(bool) $translated || $locale === 'en',
        ]);
    }

    public function posts(): View
    {
        return view('marketplace.posts', [
            'entries' => ContentEntry::where('kind','post')->where('status','published')
                ->latest('published_at')->paginate(12),
        ]);
    }

    public function policies(): View
    {
        return view('marketplace.policies', [
            'entries' => ContentEntry::where('kind','policy')->where('status','published')
                ->orderBy('title')->get(),
        ]);
    }
}
