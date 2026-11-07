<?php

namespace App\Http\Controllers;

use App\Support\AuditTrail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthSecurityController extends Controller
{
    public function forgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:190']]);
        if (! config('samith.email_delivery_enabled')) {
            return back()->withErrors(['email'=>'Password recovery is not available until secure email delivery is configured.']);
        }

        // Never reveal whether an email address is registered.
        Password::sendResetLink(['email' => Str::lower(trim((string) $request->input('email')))]);

        return back()->with('success','If an account exists for that address, a reset link has been sent.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token'=>$token, 'email'=>$request->query('email','')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'=>['required','email','max:190'],
            'token'=>['required','string'],
            'password'=>['required','confirmed',PasswordRule::min(12)->letters()->numbers()],
        ]);
        $status = Password::reset($data, function ($user, $password) use ($request): void {
            $user->forceFill([
                'password'=>Hash::make($password),
                'remember_token'=>Str::random(60),
            ])->save();
            event(new PasswordReset($user));
            AuditTrail::record($request,'auth.password.reset',$user);
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email'=>'This password reset link is invalid or has expired.']);
        }

        return redirect()->route('login')->with('success','Your password has been updated. Sign in using your new password.');
    }

    public function notice(): View
    {
        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();
        AuditTrail::record($request,'auth.email.verified',$request->user());
        return redirect()->route('account')->with('success','Your email address is verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account');
        }
        if (! config('samith.email_delivery_enabled')) {
            return back()->withErrors(['email'=>'Email verification requires working SMTP or a verified mail provider.']);
        }
        $request->user()->sendEmailVerificationNotification();
        return back()->with('success','A new verification link has been requested.');
    }
}
