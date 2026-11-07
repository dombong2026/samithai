<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        if (! Auth::attempt([
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
        ], (bool) ($data['remember'] ?? false))) {
            return back()->withErrors(['email' => 'The email address or password is incorrect.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        AuditTrail::record($request,'auth.login',$request->user());

        return redirect()->intended(auth()->user()->isAdmin() ? route('dashboard') : route('account'))
            ->with('success', 'Signed in successfully.');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
        ]);
        Auth::login($user);
        $request->session()->regenerate();
        AuditTrail::record($request,'auth.register',$user);
        if (config('samith.email_delivery_enabled')) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('account')->with('success', 'Your account was created.');
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditTrail::record($request,'auth.logout',$request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Signed out successfully.');
    }
}
