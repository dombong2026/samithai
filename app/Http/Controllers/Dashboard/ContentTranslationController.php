<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\ContentTranslation;
use App\Support\AuditTrail;
use App\Support\SafeHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContentTranslationController extends Controller
{
    private function admin(Request $request): void { abort_unless($request->user()?->isAdmin(),403); }

    private function locale(string $locale): void
    {
        abort_unless($locale !== 'en' && array_key_exists($locale,config('samith.locales')),404);
    }

    public function index(Request $request, ContentEntry $entry): View
    {
        $this->admin($request);

        return view('dashboard.translations', [
            'entry'=>$entry->load('translations.reviewer'),
            'locales'=>array_diff_key(config('samith.locales'),['en'=>true]),
        ]);
    }

    public function update(Request $request, ContentEntry $entry, string $locale): RedirectResponse
    {
        $this->admin($request);
        $this->locale($locale);
        $data=$request->validate([
            'title'=>['required','string','max:160'],
            'excerpt'=>['nullable','string','max:3000'],
            'body_html'=>['required','string','max:180000'],
        ]);
        $translated=ContentTranslation::updateOrCreate(
            ['content_entry_id'=>$entry->id,'locale'=>$locale],
            [
                ...$data,
                'body_html'=>SafeHtml::clean($data['body_html']),
                'status'=>'draft','reviewed_at'=>null,'reviewed_by'=>null,'published_at'=>null,
            ]
        );
        AuditTrail::record($request,'content.translation.save',$translated,['locale'=>$locale]);

        return back()->with('success','Translation draft saved. A reviewer must approve before publication.');
    }

    public function review(Request $request, ContentEntry $entry, string $locale): RedirectResponse
    {
        $this->admin($request);
        $this->locale($locale);
        $translation=ContentTranslation::where('content_entry_id',$entry->id)
            ->where('locale',$locale)->firstOrFail();

        $data=$request->validate(['decision'=>['required',Rule::in(['approve','revoke'])]]);
        if ($data['decision'] === 'approve') {
            abort_unless($entry->status === 'published',422,'Publish the source document before its translation.');
            $translation->update(['status'=>'published','reviewed_at'=>now(),
                'reviewed_by'=>$request->user()->id,'published_at'=>now()]);
        } else {
            $translation->update(['status'=>'draft','reviewed_at'=>null,
                'reviewed_by'=>null,'published_at'=>null]);
        }
        AuditTrail::record($request,'content.translation.review',$translation,['decision'=>$data['decision']]);

        return back()->with('success','Translation status updated.');
    }
}
