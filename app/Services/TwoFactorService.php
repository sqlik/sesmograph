<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrCodeSvg(string $email, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name'), $email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(220, 0),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($url);
    }

    /**
     * Verify a TOTP code. Returns the new accepted timestamp counter on
     * success (to persist for replay protection), or false on failure.
     */
    public function verify(string $secret, string $code, ?int $lastTimestamp): int|false
    {
        $result = $this->google2fa->verifyKeyNewer($secret, $code, $lastTimestamp);

        // verifyKeyNewer returns true (not a timestamp) when no previous
        // timestamp exists; normalize to the current counter.
        if ($result === true) {
            return (int) floor(time() / 30);
        }

        return $result === false ? false : (int) $result;
    }

    /** @return list<string> */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)))
            ->all();
    }

    /**
     * Consume a recovery code if it matches. Returns true and removes the
     * code from the user's set on success.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->recovery_codes ?? [];
        $normalized = Str::upper(trim($code));

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $normalized)) {
                unset($codes[$index]);
                $user->forceFill(['recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
