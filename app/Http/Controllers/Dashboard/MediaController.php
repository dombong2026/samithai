<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\MediaAsset;
use App\Models\Prompt;
use App\Models\SiteSetting;
use App\Models\SeoMetadata;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MediaController extends Controller
{
    private const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm'];

    private function authorizeEditor(Request $request): void
    {
        abort_unless($request->user()?->isCreator(), 403);
    }

    private function authorizeAsset(Request $request, MediaAsset $mediaAsset): void
    {
        abort_unless($request->user()?->isAdmin() || $mediaAsset->user_id === $request->user()?->id, 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(['image','video','youtube','color'])],
        ]);
        $query = MediaAsset::query()->with('owner');
        if (! $request->user()->isAdmin() || $request->routeIs('account.media')) {
            $query->where('user_id', $request->user()->id);
        }
        if (filled($data['q'] ?? null)) $query->where('title', 'like', '%'.$data['q'].'%');
        if (filled($data['kind'] ?? null)) $query->where('kind', $data['kind']);

        return view('dashboard.media', [
            'assets' => $query->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'file' => ['required', 'file', 'max:15360', 'mimetypes:'.implode(',', self::MIME_TYPES)],
        ]);

        $file = $data['file'];
        $mime = $file->getMimeType();
        abort_unless(in_array($mime, self::MIME_TYPES, true), 422);
        $kind = str_starts_with($mime, 'image/') ? 'image' : 'video';
        $path = $file->store('media/'.$request->user()->id, 'local');
        if ($path === false) return back()->withErrors(['file' => 'Unable to store media on this server.']);

        try {
            $asset = MediaAsset::create([
                'user_id' => $request->user()->id,
                'title' => $data['title'] ?? mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 160),
                'kind' => $kind,
                'mime' => $mime,
                'file_path' => $path,
                'bytes' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        AuditTrail::record($request, 'media.upload', $asset);

        return back()->with('success', 'Media uploaded securely.');
    }

    public function reference(Request $request): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'kind' => ['required', 'in:youtube,color'],
            'url' => ['nullable', 'required_if:kind,youtube', 'url', 'max:500'],
            'color_hex' => ['nullable', 'required_if:kind,color', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $youtubeId = null;
        if ($data['kind'] === 'youtube') {
            $parts = parse_url($data['url']);
            $host = strtolower($parts['host'] ?? '');
            $youtubeId = null;
            if (! in_array($parts['scheme'] ?? '', ['https'], true)) {
                return back()->withErrors(['url'=>'Use a secure YouTube URL.']);
            }
            if (in_array($host, ['youtube.com','www.youtube.com','m.youtube.com'], true) && ($parts['path'] ?? '') === '/watch') {
                parse_str($parts['query'] ?? '', $qs);
                $youtubeId = $qs['v'] ?? null;
            } elseif (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
                $youtubeId = ltrim($parts['path'] ?? '', '/');
            }
            if (! is_string($youtubeId) || ! preg_match('/^[A-Za-z0-9_-]{11}$/D', $youtubeId)) {
                return back()->withErrors(['url'=>'Enter a valid YouTube watch or youtu.be URL.']);
            }
        }

        $asset = MediaAsset::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'kind' => $data['kind'],
            'youtube_id' => $youtubeId,
            'color_hex' => $data['kind'] === 'color' ? strtoupper($data['color_hex']) : null,
        ]);
        AuditTrail::record($request, 'media.reference', $asset);

        return back()->with('success', 'Featured media reference saved.');
    }

    public function update(Request $request, MediaAsset $mediaAsset): RedirectResponse
    {
        $this->authorizeEditor($request);
        $this->authorizeAsset($request, $mediaAsset);
        $data = $request->validate(['title' => ['required', 'string', 'max:160']]);
        $mediaAsset->update($data);
        AuditTrail::record($request, 'media.update', $mediaAsset);

        return back()->with('success', 'Media details updated.');
    }

    public function destroy(Request $request, MediaAsset $mediaAsset): RedirectResponse
    {
        $this->authorizeEditor($request);
        $this->authorizeAsset($request, $mediaAsset);
        if (Prompt::where('featured_media_id', $mediaAsset->id)->exists()
            || ContentEntry::where('featured_media_id', $mediaAsset->id)->exists()
            || SeoMetadata::where('og_media_id', $mediaAsset->id)->exists()
            || SiteSetting::text('logo_media_id') === (string) $mediaAsset->id) {
            return back()->withErrors(['media' => 'Remove this asset from featured content before deleting it.']);
        }
        $path = $mediaAsset->file_path;
        $mediaAsset->delete();
        if ($path) Storage::disk('local')->delete($path);
        AuditTrail::record($request, 'media.delete', $mediaAsset);

        return back()->with('success', 'Media deleted.');
    }

    public function serve(Request $request, MediaAsset $mediaAsset)
    {
        if (! in_array($mediaAsset->kind, ['image', 'video'], true) || ! $mediaAsset->file_path) abort(404);

        $public = Prompt::published()->where('featured_media_id', $mediaAsset->id)->exists()
            || ContentEntry::where('featured_media_id', $mediaAsset->id)->where('status','published')->exists();
        $public = $public ||
            \App\Models\SeoMetadata::query()
                ->where('og_media_id',$mediaAsset->id)
                ->where(function ($q): void {
                    $q->whereHas('contentEntry',fn ($builder) => $builder->where('status','published'))
                        ->orWhereHas('prompt',fn ($builder) => $builder->where('status','published'));
                })->exists();
        $public = $public || (SiteSetting::text('logo_media_id') === (string) $mediaAsset->id);
        // Only an actually published record can expose its sharing image.
        // Draft-only OG assets remain private until editorial publication.
        if (!$public && $mediaAsset->kind === 'image') {
            $public = SeoMetadata::query()->where('og_media_id', $mediaAsset->id)
                ->where(function ($query): void {
                    $query->whereHas('contentEntry', fn ($q) => $q->where('status','published'))
                        ->orWhereHas('prompt', fn ($q) => $q->where('status','published'));
                })->exists();
        }
        $allowed = $public || ($request->user()
            && ($request->user()->isAdmin() || $request->user()->id === $mediaAsset->user_id));
        abort_unless($allowed, 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($mediaAsset->file_path), 404);
        abort_unless(in_array($mediaAsset->mime, self::MIME_TYPES, true), 415);

        return response()->file($disk->path($mediaAsset->file_path), [
            'Content-Type' => $mediaAsset->mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => $public ? 'public, max-age=600' : 'private, no-store',
        ]);
    }
}
