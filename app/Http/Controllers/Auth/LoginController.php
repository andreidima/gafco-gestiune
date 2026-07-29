<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $loginField = $request->has('login_code') ? 'login_code' : 'email';
        $credentials = $request->validate([
            $loginField => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($credentials[$loginField]);
        $normalizedLogin = Str::lower($login);
        $throttleKey = $normalizedLogin.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                $loginField => 'Prea multe incercari. Incearca din nou peste '.RateLimiter::availableIn($throttleKey).' secunde.',
            ]);
        }

        $user = User::query()
            ->where('active', true)
            ->where(function ($query) use ($normalizedLogin): void {
                $query->whereRaw('LOWER(email) = ?', [$normalizedLogin]);

                if (Schema::hasColumn('users', 'login_code')) {
                    $query->orWhereRaw('LOWER(login_code) = ?', [$normalizedLogin]);
                }
            })
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors([$loginField => 'Codul, emailul, parola sau starea contului nu sunt corecte.'])
                ->onlyInput($loginField);
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(
        Request $request,
        ImpersonationService $impersonation,
    ): RedirectResponse {
        $impersonation->endBeforeLogout($request);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
