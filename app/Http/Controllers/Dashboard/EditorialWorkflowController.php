<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContentEntry;
use App\Models\ContentEditorialRevision;
use App\Support\SafeHtml;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\EditorialGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EditorialWorkflowController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(),403);
    }

    private function editable(ContentEntry $entry): void
    {
        abort_unless(in_array($entry->kind, ['post','page'], true), 404);
    }

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'kind' => ['nullable',Rule::in(['post','page'])],
            'status' => ['nullable',Rule::in(['draft','in_review','changes_requested','approved','scheduled'])],
            'q' => ['nullable','string','max:100'],
        ]);
        $query = ContentEntry::query()
            ->with(['reviewer','author','media'])
            ->whereIn('kind',['post','page']);
        if (filled($data['kind'] ?? null)) $query->where('kind',$data['kind']);
        if (filled($data['status'] ?? null)) $query->where('editorial_status',$data['status']);
        if (filled($data['q'] ?? null)) $query->where('title','like','%'.$data['q'].'%');

        return view('dashboard.editorial.index', [
            'entries' => $query->latest('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function show(Request $request, ContentEntry $entry): View
    {
        $this->authorizeAdmin($request);
        $this->editable($entry);

        return view('dashboard.editorial.show', [
            'entry' => $entry->load(['reviewer','author','media']),
            'reviewers' => User::query()->where('role','admin')->orderBy('name')->get(['id','name','email']),
            'revisions' => $entry->editorialRevisions()->with('actor')->latest('id')->limit(30)->get(),
            'approvedCurrent' => EditorialGate::approved($entry),
        ]);
    }

    public function submit(Request $request, ContentEntry $entry): RedirectResponse
    {
        $this->authorizeAdmin($request); $this->editable($entry);
        if ($entry->status !== 'draft' || !in_array($entry->editorial_status,['draft','changes_requested'],true)) {
            return back()->withErrors(['editorial'=>'Only draft or revision-requested content can be submitted.']);
        }
        $entry->update([
            'editorial_status' => 'in_review',
            'editorial_approved_at' => null,
            'editorial_approved_hash' => null,
            'scheduled_for' => null,
        ]);
        EditorialGate::revision($entry,$request->user()->id,'submit_review');
        AuditTrail::record($request,'content.editorial.submit',$entry);
        return back()->with('success','Content submitted for human review.');
    }

    public function assign(Request $request, ContentEntry $entry): RedirectResponse
    {
        $this->authorizeAdmin($request); $this->editable($entry);
        $data = $request->validate([
            'reviewer_id' => [
                'required','integer',
                Rule::exists('users','id')->where('role','admin'),
            ],
        ]);
        if ($entry->status !== 'draft') {
            return back()->withErrors(['reviewer_id'=>'Only unpublished drafts can be assigned.']);
        }
        if (in_array($entry->editorial_status,['approved','scheduled'],true)) {
            EditorialGate::invalidate($entry);
        }
        $entry->update(['reviewer_id'=>$data['reviewer_id']]);
        EditorialGate::revision($entry,$request->user()->id,'assign_reviewer');
        AuditTrail::record($request,'content.editorial.assign',$entry,['reviewer_id'=>$data['reviewer_id']]);
        return back()->with('success','Reviewer assigned. Record approval only after actual review.');
    }

    public function decide(Request $request, ContentEntry $entry): RedirectResponse
    {
        $this->authorizeAdmin($request); $this->editable($entry);
        $data = $request->validate([
            'decision' => ['required',Rule::in(['approve','request_changes'])],
            'notes' => ['required','string','min:20','max:3000'],
        ]);
        if ($entry->status !== 'draft' || $entry->editorial_status !== 'in_review') {
            return back()->withErrors(['decision'=>'Only submitted unpublished content can be reviewed.']);
        }
        if ((int) $entry->reviewer_id !== (int) $request->user()->id) {
            abort(403,'The assigned reviewer must record this decision.');
        }
        $approved = $data['decision'] === 'approve';
        $entry->update([
            'editorial_status' => $approved ? 'approved' : 'changes_requested',
            'editorial_approved_at' => $approved ? now() : null,
            'editorial_approved_hash' => $approved ? EditorialGate::fingerprint($entry) : null,
            'scheduled_for' => null,
        ]);
        EditorialGate::revision($entry,$request->user()->id,
            $approved ? 'approve' : 'request_changes');
        // Reviewer notes are in the protected audit details only; never rendered publicly.
        AuditTrail::record($request,'content.editorial.decision',$entry,[
            'decision' => $data['decision'],
            'notes' => $data['notes'],
            'self_review' => (int)$entry->user_id === (int)$request->user()->id,
        ]);
        return back()->with('success',$approved ? 'Approval recorded.' : 'Revisions requested.');
    }

    public function schedule(Request $request, ContentEntry $entry): RedirectResponse
    {
        $this->authorizeAdmin($request); $this->editable($entry);
        $data = $request->validate([
            'scheduled_for' => ['required','date','after:now'],
        ]);
        $scheduled = \Illuminate\Support\Carbon::parse($data['scheduled_for']);
        if ($entry->status !== 'draft' || !EditorialGate::approved($entry)) {
            return back()->withErrors(['scheduled_for'=>'A current human approval is required before scheduling.']);
        }
        $entry->update(['editorial_status'=>'scheduled','scheduled_for'=>$scheduled]);
        EditorialGate::revision($entry,$request->user()->id,'schedule');
        AuditTrail::record($request,'content.editorial.schedule',$entry,['scheduled_for'=>$scheduled->toIso8601String()]);
        return back()->with('success','Publication scheduled; the Laravel scheduler must be operational.');
    }

    public function revision(Request $request, ContentEntry $entry, ContentEditorialRevision $revision): View
    {
        $this->authorizeAdmin($request); $this->editable($entry);
        abort_unless((int) $revision->content_entry_id === (int) $entry->getKey(),404);
        $snapshot = json_decode((string)$revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
        return view('dashboard.editorial.revision', [
            'entry'=>$entry,'revision'=>$revision->load('actor'), 'snapshot'=>$snapshot,
        ]);
    }

    public function restore(Request $request, ContentEntry $entry, ContentEditorialRevision $revision): RedirectResponse
    {
        $this->authorizeAdmin($request); $this->editable($entry);
        abort_unless((int) $revision->content_entry_id === (int) $entry->getKey(),404);
        if ($entry->status !== 'draft') {
            return back()->withErrors(['revision'=>'Unpublish before restoring historical content.']);
        }
        DB::transaction(function () use ($entry,$revision,$request): void {
            $current = ContentEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
            if ($current->status !== 'draft') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'revision'=>'Only unpublished drafts may be restored.',
                ]);
            }
            $snapshot=json_decode((string)$revision->snapshot,true,flags:JSON_THROW_ON_ERROR);
            if (!is_array($snapshot)
                || ($snapshot['slug'] ?? null) !== $current->slug
                || ($snapshot['kind'] ?? null) !== $current->kind
                || !is_string($snapshot['title'] ?? null)
                || mb_strlen($snapshot['title']) > 160
                || !is_string($snapshot['body_html'] ?? null)
                || strlen($snapshot['body_html']) > 180000) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'revision'=>'The stored revision is incompatible with the current document.',
                ]);
            }
            EditorialGate::revision($current,$request->user()->id,'before_restore');
            $current->update([
                'title'=>$snapshot['title'],
                'excerpt'=>$snapshot['excerpt'] ?? null,
                'body_html'=>SafeHtml::clean($snapshot['body_html']),
                'featured_media_id'=>$snapshot['featured_media_id'] ?? null,
            ]);
            EditorialGate::invalidate($current);
            EditorialGate::revision($current,$request->user()->id,'after_restore');
            AuditTrail::record($request,'content.editorial.restore',$current,[
                'revision_id'=>$revision->getKey(),'approval_invalidated'=>true,
            ]);
        },3);
        return redirect()->route('dashboard.editorial.show',$entry)
            ->with('success','Prior version restored as a draft. Submit it for fresh review.');
    }

    public function cancelSchedule(Request $request, ContentEntry $entry): RedirectResponse
    {
        $this->authorizeAdmin($request); $this->editable($entry);
        if ($entry->status !== 'draft' || $entry->editorial_status !== 'scheduled'
            || !EditorialGate::approved($entry)) {
            return back()->withErrors(['scheduled_for'=>'Only a currently approved, scheduled draft can be cancelled.']);
        }
        $entry->update(['editorial_status'=>'approved','scheduled_for'=>null]);
        EditorialGate::revision($entry,$request->user()->id,'cancel_schedule');
        AuditTrail::record($request,'content.editorial.cancel_schedule',$entry);
        return back()->with('success','Schedule cancelled; approval retained for the unchanged content.');
    }
}
