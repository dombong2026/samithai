<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Support\MailDelivery;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $connected = false;
        try {
            DB::select('SELECT 1');
            $connected = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return view('dashboard.system', [
            'requirements' => [
                'PHP 8.3+' => PHP_VERSION_ID >= 80300,
                'PDO MySQL' => extension_loaded('pdo_mysql'),
                'OpenSSL' => extension_loaded('openssl'),
                'Mbstring' => extension_loaded('mbstring'),
                'XML / DOM' => extension_loaded('dom') && extension_loaded('xml'),
                'Fileinfo' => extension_loaded('fileinfo'),
                'Storage writable' => is_writable(storage_path('app')) && is_writable(storage_path('framework')),
                'Bootstrap cache writable' => is_writable(base_path('bootstrap/cache')),
                'Database responds' => $connected,
                'Installation locked' => is_file(storage_path('app/installed.lock')),
                'Debug disabled' => !config('app.debug'),
                'HTTPS request' => $request->secure(),
                'Email delivery configured without logging' => MailDelivery::permitted(),
            ],
            'runtime' => [
                'Laravel version' => app()->version(),
                'PHP version' => PHP_VERSION,
                'Environment' => app()->environment(),
                'Queue mode' => config('queue.default'),
                'Cache driver' => config('cache.default'),
                'Disk' => config('filesystems.default'),
            ],
        ]);
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
