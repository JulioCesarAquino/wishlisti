<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\SignInRequest;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The sign-in page, in the look of the home page and the sign-up. The
 * panel's own login (/admin/login) sends people here.
 */
class SignInController extends Controller
{
    /** Attempts per e-mail and visitor, per minute (as the panel's login). */
    private const MAX_ATTEMPTS = 5;

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect('/admin');
        }

        return Inertia::render('entrar', [
            'password_reset_url' => Filament::getPanel('admin')->getRequestPasswordResetUrl(),
        ]);
    }

    /**
     * Signs in and goes where the person was heading (a panel page that
     * asked for it), or to the panel — outside Inertia: a full page load.
     */
    public function store(SignInRequest $request): SymfonyResponse
    {
        $key = 'sign-in:'.Str::transliterate($request->validated('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Muitas tentativas. Tente de novo em '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        // The same time whether the e-mail exists or not: no telling them apart.
        $signedIn = app(Timebox::class)->call(function (Timebox $timebox) use ($request): bool {
            $ok = Auth::attempt(
                ['email' => $request->validated('email'), 'password' => $request->validated('password')],
                $request->boolean('remember'),
            );

            if ($ok) {
                $timebox->returnEarly();
            }

            return $ok;
        }, (int) config('auth.timebox_duration', 200_000));

        if (! $signedIn) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha incorretos.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return Inertia::location(redirect()->intended('/admin')->getTargetUrl());
    }
}
