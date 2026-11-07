<?php

namespace App\Http\Controllers;

use App\Models\ContentEntry;
use App\Models\SupportTicket;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupportController extends Controller
{
    private function authorizeTicket(Request $request, SupportTicket $ticket): void
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->id === $ticket->user_id, 403);
    }

    public function center(): View
    {
        return view('support.center', [
            'articles' => ContentEntry::where('kind','article')->where('status','published')
                ->orderBy('title')->paginate(10),
        ]);
    }

    public function tickets(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable','string','max:100'],
            'status' => ['nullable','in:open,closed'],
        ]);
        $admin = $request->routeIs('dashboard.support');
        abort_if($admin && ! $request->user()->isAdmin(), 403);
        $query = SupportTicket::with('user')->withCount('messages');
        if (! $admin) $query->where('user_id', $request->user()->id);
        if (filled($data['q'] ?? null)) $query->where('subject','like','%'.$data['q'].'%');
        if (filled($data['status'] ?? null)) $query->where('status',$data['status']);

        return view('support.tickets', [
            'tickets' => $query->latest()->paginate(10)->withQueryString(),
            'admin' => $admin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required','string','max:160'],
            'body' => ['required','string','min:10','max:10000'],
        ]);

        $ticket = DB::transaction(function () use ($request, $data): SupportTicket {
            $ticket = SupportTicket::create([
                'user_id' => $request->user()->id,
                'subject' => $data['subject'],
                'status' => 'open',
            ]);
            $ticket->messages()->create(['user_id'=>$request->user()->id, 'body'=>$data['body']]);
            return $ticket;
        });
        AuditTrail::record($request,'support.ticket.create',$ticket);

        return redirect()->route('support.show',$ticket)->with('success','Support ticket opened.');
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        $this->authorizeTicket($request,$ticket);
        return view('support.show', [
            'ticket' => $ticket->load(['user','messages.user']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($request,$ticket);
        abort_unless($ticket->status === 'open', 422);
        $data = $request->validate(['body'=>['required','string','min:2','max:10000']]);
        $ticket->messages()->create(['user_id'=>$request->user()->id,'body'=>$data['body']]);
        AuditTrail::record($request,'support.ticket.reply',$ticket);
        return back()->with('success','Reply added.');
    }

    public function status(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeTicket($request,$ticket);
        $data = $request->validate(['status'=>['required','in:open,closed']]);
        if ($data['status'] === 'open') abort_unless($request->user()->isAdmin(),403);
        $ticket->update(['status'=>$data['status']]);
        AuditTrail::record($request,'support.ticket.status',$ticket,['status'=>$data['status']]);
        return back()->with('success','Ticket status updated.');
    }
}
