<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('account.index', [
            'libraryCount' => $user->entitlements()->count(),
            'cartCount' => $user->cartItems()->count(),
            'ordersCount' => $user->isAdmin() ? 0 : \App\Models\Order::where('user_id', $user->id)->count(),
            'promptsCount' => $user->prompts()->count(),
        ]);
    }

    public function library(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = $request->user()->entitlements()->with('prompt.category');
        if (filled($data['q'] ?? null)) {
            $query->whereHas('prompt', fn ($builder) => $builder->where('title', 'like', '%'.$data['q'].'%'));
        }

        return view('account.library', [
            'entitlements' => $query->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function purchasedPrompt(Request $request, Prompt $prompt): View
    {
        $allowed = $request->user()->isAdmin()
            || $prompt->user_id === $request->user()->id
            || $request->user()->entitlements()->where('prompt_id', $prompt->id)->exists();

        abort_unless($allowed, 403);

        $entitlement = $request->user()->entitlements()->where('prompt_id',$prompt->id)->first();
        $content = ($request->user()->isAdmin() || $prompt->user_id === $request->user()->id)
            ? $prompt->content
            : ($entitlement?->content_snapshot ?? $prompt->content);

        return view('account.prompt', [
            'prompt' => $prompt,
            'content' => $content,
            'license' => $entitlement?->license_type ?? $prompt->license_type,
        ]);
    }

    public function settings(): View
    {
        return view('account.settings');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        if ($user->email !== strtolower(trim($data['email']))) {
            $user->email_verified_at = null;
        }
        $user->name = $data['name'];
        $user->email = strtolower(trim($data['email']));
        $user->save();
        AuditTrail::record($request,'account.profile.update',$user);
        if ($user->email_verified_at === null && config('samith.email_delivery_enabled')) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('success', 'Account details saved.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }
        $request->user()->update(['password' => $data['password'], 'remember_token' => Str::random(60)]);
        AuditTrail::record($request,'account.password.update',$request->user());

        return back()->with('success', 'Password changed successfully.');
    }

    public function becomeCreator(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->role === 'customer') {
            $user->role = 'creator';
            $user->save();
        }

        return redirect()->route('account.prompts')->with('success', 'Creator workspace enabled.');
    }
}
