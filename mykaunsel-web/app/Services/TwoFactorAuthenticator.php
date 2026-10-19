<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) two-factor authentication: secrets, QR codes, code checks and recovery codes.
 * The secret and the stored recovery-code hashes live in encrypted users columns.
 */
class TwoFactorAuthenticator
{
    public const RECOVERY_CODE_COUNT = 8;

    /** Accept the current 30s code plus one step either side (clock drift). */
    private const WINDOW = 1;

    public function __construct(private Google2FA $google = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->google->generateSecretKey(32);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google->getQRCodeUrl(config('app.name', 'MyKaunsel'), $user->email, $secret);
    }

    /** Inline-safe SVG of the QR code (no XML prolog), generated locally — the secret never leaves our server. */
    public function qrSvg(string $otpauthUrl, int $size = 192): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($otpauthUrl);

        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }

    /** Secret in groups of four for typing into an authenticator by hand. */
    public function manualKey(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    /**
     * Check a 6-digit code against the user's secret. A code is accepted only once:
     * the matched time-step is stored and anything not newer is rejected.
     */
    public function verify(User $user, ?string $code): bool
    {
        $code = preg_replace('/\D/', '', (string) $code);

        if (strlen($code) !== 6 || ! $user->two_factor_secret) {
            return false;
        }

        $step = $this->google->verifyKeyNewer($user->two_factor_secret, $code, (int) $user->two_factor_last_timestamp, self::WINDOW);

        if ($step === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_timestamp' => $step])->save();

        return true;
    }

    /** @return list<string> plain-text codes — show once, store only hashes */
    public function newRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => strtolower(\Illuminate\Support\Str::random(5).'-'.\Illuminate\Support\Str::random(5)))
            ->all();
    }

    /** Replace the user's recovery codes; returns the plain codes to show once. */
    public function issueRecoveryCodes(User $user): array
    {
        $codes = $this->newRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode(array_map(fn ($c) => $this->hashCode($c), $codes)),
        ])->save();

        return $codes;
    }

    /** Use (and burn) a recovery code. */
    public function consumeRecoveryCode(User $user, ?string $input): bool
    {
        $input = strtolower(trim((string) $input));

        if ($input === '') {
            return false;
        }

        $hash = $this->hashCode($input);
        $remaining = $user->recoveryCodeHashes();
        $index = null;

        foreach ($remaining as $i => $stored) {
            if (hash_equals($stored, $hash)) {
                $index = $i;
            }
        }

        if ($index === null) {
            return false;
        }

        unset($remaining[$index]);
        $user->forceFill(['two_factor_recovery_codes' => json_encode(array_values($remaining))])->save();

        return true;
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', strtolower(trim($code)), (string) config('app.key'));
    }
}
