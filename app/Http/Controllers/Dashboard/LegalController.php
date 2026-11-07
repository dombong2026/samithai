<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\LegalVersion;
use App\Support\AuditTrail;
use App\Support\SafeHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LegalController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(),403);
    }

    public function index(Request $request): View
    {
        $this->admin($request);
        $data=$request->validate([
            'q'=>['nullable','string','max:100'],
            'kind'=>['nullable',Rule::in(LegalVersion::TYPES)],
            'status'=>['nullable','in:draft,published,retired'],
        ]);
        $query=LegalVersion::query();
        if (filled($data['q']??null)) $query->where('title','like','%'.$data['q'].'%');
        if (filled($data['kind']??null)) $query->where('kind',$data['kind']);
        if (filled($data['status']??null)) $query->where('status',$data['status']);

        return view('dashboard.legal', [
            'documents'=>$query->latest()->paginate(10)->withQueryString(),
            'kinds'=>LegalVersion::TYPES,
        ]);
    }

    private function validateDocument(Request $request): array
    {
        return $request->validate([
            'kind'=>['required',Rule::in(LegalVersion::TYPES)],
            'version'=>['required','regex:/^[0-9]{1,4}\.[0-9]{1,4}\.[0-9]{1,4}$/D'],
            'title'=>['required','string','max:160'],
            'body_html'=>['required','string','min:100','max:180000'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data=$this->validateDocument($request);
        if (LegalVersion::where('kind',$data['kind'])->where('version',$data['version'])->exists()) {
            return back()->withErrors(['version'=>'A version with that number already exists for this document type.']);
        }
        $entry=LegalVersion::create([...$data,'body_html'=>SafeHtml::clean($data['body_html']),'status'=>'draft']);
        AuditTrail::record($request,'legal.draft.create',$entry);
        return back()->with('success','Legal draft created. Independent review is required.');
    }

    public function update(Request $request, LegalVersion $document): RedirectResponse
    {
        $this->admin($request);
        abort_unless($document->status==='draft',422,'Published and retired legal versions are immutable.');
        $data=$this->validateDocument($request);
        if (LegalVersion::where('kind',$data['kind'])->where('version',$data['version'])->whereKeyNot($document->id)->exists()) {
            return back()->withErrors(['version'=>'That version already exists.']);
        }
        $document->update([...$data,'body_html'=>SafeHtml::clean($data['body_html'])]);
        AuditTrail::record($request,'legal.draft.update',$document);
        return back()->with('success','Legal draft updated.');
    }

    public function publish(Request $request, LegalVersion $document): RedirectResponse
    {
        $this->admin($request);
        $data=$request->validate([
            'review_reference'=>['required','string','min:8','max:255'],
            'acknowledged'=>['accepted'],
        ]);
        abort_unless($document->status==='draft',422,'Only drafts can be published.');
        DB::transaction(function () use ($request,$document,$data): void {
            LegalVersion::where('kind',$document->kind)->where('status','published')
                ->lockForUpdate()->update(['status'=>'retired']);
            $document->update([
                'status'=>'published','review_reference'=>$data['review_reference'],
                'reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'effective_at'=>now(),
            ]);
        },3);
        AuditTrail::record($request,'legal.publish',$document,['kind'=>$document->kind,'version'=>$document->version]);
        return back()->with('success','Version published with the review reference recorded.');
    }

    public function retire(Request $request, LegalVersion $document): RedirectResponse
    {
        $this->admin($request);
        abort_unless($document->status==='published',422);
        $document->update(['status'=>'retired']);
        AuditTrail::record($request,'legal.retire',$document);
        return back()->with('success','Legal version retired; prior acceptance records remain.');
    }

    public function public(string $kind): View
    {
        abort_unless(in_array($kind,LegalVersion::TYPES,true),404);
        $document=LegalVersion::current($kind);
        abort_unless($document,404);
        return view('marketplace.legal',compact('document'));
    }
}
