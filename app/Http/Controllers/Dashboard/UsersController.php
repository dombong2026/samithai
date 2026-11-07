<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UsersController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    public function index(Request $request): View
    {
        $this->admin($request);
        $data = $request->validate([
            'q'=>['nullable','string','max:100'],
            'role'=>['nullable','in:admin,creator,customer'],
        ]);
        $query = User::query();
        if (filled($data['q'] ?? null)) {
            $query->where(function ($builder) use ($data) {
                $builder->where('name','like','%'.$data['q'].'%')
                    ->orWhere('email','like','%'.$data['q'].'%');
            });
        }
        if (filled($data['role'] ?? null)) $query->where('role',$data['role']);

        return view('dashboard.users', [
            'users' => $query->latest()->paginate(10)->withQueryString(),
            'adminCount' => User::where('role','admin')->count(),
        ]);
    }

    public function role(Request $request, User $user): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate(['role'=>['required','in:admin,creator,customer']]);
        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            return back()->withErrors(['role'=>'You cannot remove your own administrator role.']);
        }

        $before = null;
        DB::transaction(function () use ($user, $data, &$before): void {
            // Lock role changes to the selected account. Self-demotion is always blocked above.
            $record = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before = $record->role;
            $record->role = $data['role'];
            $record->save();
        });
        AuditTrail::record($request,'user.role.update',$user,['from'=>$before,'to'=>$data['role']]);

        return back()->with('success','Account role updated.');
    }
}
