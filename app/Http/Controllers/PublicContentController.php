<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use Illuminate\View\View;

class PublicContentController extends Controller
{
    public function show(string $kind, string $slug): View
    {
        abort_unless(in_array($kind, ['page','post','policy','article'], true), 404);
        $entry = ContentEntry::with('media')->where('kind', $kind)
            ->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('marketplace.content', ['entry' => $entry, 'preview' => false]);
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
