<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    private function googleEnabled(): bool
    {
        return ! empty(config('services.google.client_id')) && ! empty(config('services.google.client_secret'));
    }

    /** Designs made before signing in belong to this account now ("My designs"). */
    private function claimDesigns(): void
    {
        $ids = array_keys(session('design.projects', []));
        if ($ids && Auth::id()) {
            \App\Models\DesignProject::whereIn('id', $ids)->whereNull('user_id')->update(['user_id' => Auth::id()]);
        }
    }

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('account');
        }

        return Inertia::render('Auth/Login', ['googleEnabled' => $this->googleEnabled()]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data, (bool) $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $this->claimDesigns();

        return redirect()->intended(route('account'));
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('account');
        }

        return Inertia::render('Auth/Register', ['googleEnabled' => $this->googleEnabled()]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => false,
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->claimDesigns();

        return redirect()->intended(route('account'));
    }

    public function redirectToGoogle()
    {
        abort_unless($this->googleEnabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        abort_unless($this->googleEnabled(), 404);

        try {
            $gu = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in failed. Please try again.']);
        }

        $user = User::where('google_id', $gu->getId())->first()
            ?? User::where('email', $gu->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name'      => $gu->getName() ?: ($gu->getNickname() ?: 'Customer'),
                'email'     => $gu->getEmail(),
                'google_id' => $gu->getId(),
                'avatar'    => $gu->getAvatar(),
                'is_admin'  => false,
            ]);
        } elseif (! $user->google_id) {
            $user->update(['google_id' => $gu->getId(), 'avatar' => $gu->getAvatar() ?: $user->avatar]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->claimDesigns();

        return redirect()->intended(route('account'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    // ---- password reset -------------------------------------------------------

    public function showForgotPassword()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        \Illuminate\Support\Facades\Password::sendResetLink($request->only('email'));

        // Same response whether or not the account exists — no user enumeration.
        return back()->with('success', 'If that address has an account, a reset link is on its way.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $status = \Illuminate\Support\Facades\Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->update(['password' => Hash::make($password)]);
                Auth::login($user);
            }
        );

        return $status === \Illuminate\Support\Facades\Password::PASSWORD_RESET
            ? redirect()->route('account')->with('success', 'Password updated — you are signed in.')
            : back()->withErrors(['email' => __($status)]);
    }
}
