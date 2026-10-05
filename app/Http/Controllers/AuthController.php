<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $r)
    {
        $v = $r->validate(['login' => 'required|string|max:150', 'password' => 'required|string']);
        $key = 'login:'.hash('sha256', strtolower($v['login']).'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        $field = filter_var($v['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if (! Auth::attempt([$field => $v['login'], 'password' => $v['password']])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['login' => 'Username/email atau password salah.']);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();

        return redirect()->intended(preg_match('/Android|iPhone|Mobile/i', $r->userAgent() ?? '') ? route('scanner') : route('dashboard'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function settings()
    {
        return view('settings');
    }

    public function password(Request $r)
    {
        $v = $r->validate(['current_password' => 'required|current_password', 'password' => 'required|string|min:12|confirmed']);
        $r->user()->forceFill(['password' => Hash::make($v['password'])])->save();
        Auth::logoutOtherDevices($v['password']);
        $r->session()->regenerate();

        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
