<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    protected $maxAttempts = 5;
    protected $decaySeconds = 60; // 1 minuto

    public function show()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['email' => "Demasiados intentos. Intenta de nuevo en $seconds segundos."]);
        }

        $credentials = $request->credentials();
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($key);
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        RateLimiter::hit($key, $this->decaySeconds);

        return back()->withErrors([
            'email' => 'Credenciales inválidas.',
        ])->withInput($request->only('email', 'remember'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    protected function throttleKey(Request $request)
    {
        $email = (string) $request->input('email');
        return Str::lower($email).'|'.$request->ip();
    }
}
