<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\FooterColumn;
use App\Models\FooterLink;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FooterController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function validLink(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//') && ! str_contains($url, '\\') && ! preg_match('/[\x00-\x1F]/', $url);
        }

        return filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https';
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        return view('dashboard.footer', [
            'columns' => FooterColumn::with('links')->orderBy('position')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'title' => ['required','string','max:80'],
            'position' => ['required','integer','min:1','max:5'],
            'enabled' => ['required','boolean'],
        ]);
        if (FooterColumn::count() >= 5) return back()->withErrors(['title'=>'Maximum five footer columns.']);
        $column = FooterColumn::create($data);
        AuditTrail::record($request, 'footer.column.create', $column);
        return back()->with('success','Footer column created.');
    }

    public function update(Request $request, FooterColumn $column): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'title' => ['required','string','max:80'],
            'position' => ['required','integer','min:1','max:5'],
            'enabled' => ['required','boolean'],
        ]);
        $column->update($data);
        AuditTrail::record($request,'footer.column.update',$column);
        return back()->with('success','Footer column saved.');
    }

    public function destroy(Request $request, FooterColumn $column): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $column->delete();
        AuditTrail::record($request,'footer.column.delete',$column);
        return back()->with('success','Footer column deleted.');
    }

    public function storeLink(Request $request, FooterColumn $column): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'label' => ['required','string','max:100'],
            'url' => ['required','string','max:500'],
            'position' => ['required','integer','min:1','max:999'],
        ]);
        if (! $this->validLink($data['url'])) return back()->withErrors(['url'=>'Use a relative path beginning with / or a secure https:// URL.']);
        $link = $column->links()->create($data);
        AuditTrail::record($request,'footer.link.create',$link);
        return back()->with('success','Footer link created.');
    }

    public function updateLink(Request $request, FooterLink $link): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'label' => ['required','string','max:100'],
            'url' => ['required','string','max:500'],
            'position' => ['required','integer','min:1','max:999'],
        ]);
        if (! $this->validLink($data['url'])) return back()->withErrors(['url'=>'Use a relative path beginning with / or a secure https:// URL.']);
        $link->update($data);
        AuditTrail::record($request,'footer.link.update',$link);
        return back()->with('success','Footer link updated.');
    }

    public function destroyLink(Request $request, FooterLink $link): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $link->delete();
        AuditTrail::record($request,'footer.link.delete',$link);
        return back()->with('success','Footer link deleted.');
    }
}
