<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Support\InfrastructureReadiness;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(),403);
    }

    public function security(Request $request): View
    {
        $this->admin($request);
        $data = $request->validate([
            'q' => ['nullable','string','max:100'],
            'action' => ['nullable','string','max:100'],
        ]);
        $query = AuditEvent::query()->leftJoin('users','users.id','=','audit_events.user_id')
            ->select('audit_events.*','users.name as actor_name');
        if (filled($data['q'] ?? null)) $query->where(function ($builder) use ($data) {
            $builder->where('audit_events.action','like','%'.$data['q'].'%')
                ->orWhere('users.name','like','%'.$data['q'].'%');
        });
        if (filled($data['action'] ?? null)) $query->where('audit_events.action',$data['action']);

        return view('dashboard.security', [
            'events' => $query->orderByDesc('audit_events.id')->paginate(10)->withQueryString(),
            'actions' => AuditEvent::distinct()->orderBy('action')->pluck('action'),
            'admins' => User::where('role','admin')->count(),
        ]);
    }

    public function system(Request $request): View
    {
        $this->admin($request);

        return view('dashboard.system', InfrastructureReadiness::inspect());
    }

    public function language(Request $request): View
    {
        $this->admin($request);
        return view('dashboard.language', [
            'locales' => config('samith.locales'),
            'selected' => app()->getLocale(),
        ]);
    }
}
