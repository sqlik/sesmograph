<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('two_factor.user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('two_factor');

        if ($pending === null) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $throttleKey = 'two-factor|'.$pending['user_id'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        /** @var User $user */
        $user = User::query()->findOrFail($pending['user_id']);

        if (! $this->attempt($request, $user)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'code' => 'This code is not valid.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, $pending['remember']);
        $request->session()->regenerate();
        $request->session()->forget('two_factor');

        return redirect()->intended(route('dashboard'));
    }

    private function attempt(Request $request, User $user): bool
    {
        if ($recoveryCode = $request->string('recovery_code')->trim()->value()) {
            return $this->twoFactor->consumeRecoveryCode($user, $recoveryCode);
        }

        $code = $request->string('code')->trim()->value();

        if ($code === '') {
            return false;
        }

        $timestamp = $this->twoFactor->verify($user->totp_secret, $code, $user->totp_timestamp);

        if ($timestamp === false) {
            return false;
        }

        $user->forceFill(['totp_timestamp' => $timestamp])->save();

        return true;
    }
}
