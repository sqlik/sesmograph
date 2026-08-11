<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorSetupController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasConfirmedTwoFactor()) {
            return redirect()->route('dashboard');
        }

        $secret = $request->session()->get('two_factor_setup.secret');

        if ($secret === null) {
            $secret = $this->twoFactor->generateSecret();
            $request->session()->put('two_factor_setup.secret', $secret);
        }

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'qrCode' => $this->twoFactor->qrCodeSvg($request->user()->email, $secret),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasConfirmedTwoFactor()) {
            return redirect()->route('dashboard');
        }

        $request->validate(['code' => ['required', 'string']]);

        $secret = $request->session()->get('two_factor_setup.secret');

        if ($secret === null) {
            return redirect()->route('two-factor.setup');
        }

        $timestamp = $this->twoFactor->verify($secret, $request->string('code')->trim()->value(), null);

        if ($timestamp === false) {
            throw ValidationException::withMessages([
                'code' => 'This code is not valid. Scan the QR code again and enter the current code.',
            ]);
        }

        $user->forceFill([
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
            'totp_timestamp' => $timestamp,
            'recovery_codes' => $this->twoFactor->generateRecoveryCodes(),
        ])->save();

        $request->session()->forget('two_factor_setup');

        return redirect()->route('two-factor.recovery-codes')
            ->with('status', 'Two-factor authentication is on');
    }

    public function recoveryCodes(Request $request): View
    {
        return view('auth.recovery-codes', [
            'codes' => $request->user()->recovery_codes ?? [],
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'recovery_codes' => $this->twoFactor->generateRecoveryCodes(),
        ])->save();

        return redirect()->route('two-factor.recovery-codes')
            ->with('status', 'New recovery codes generated - previous codes no longer work');
    }

    public function download(Request $request): Response
    {
        $codes = $request->user()->recovery_codes ?? [];

        return response(implode(PHP_EOL, $codes).PHP_EOL, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="sesmograph-recovery-codes.txt"',
        ]);
    }
}
