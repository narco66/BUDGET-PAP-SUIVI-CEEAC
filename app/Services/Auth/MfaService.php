<?php

namespace App\Services\Auth;

use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** Second facteur TOTP (P1-1) — activation, vérification et codes de secours. */
class MfaService
{
    public function __construct(private readonly Google2FA $google2fa)
    {
        $this->google2fa->setWindow(config('mfa.window', 1));
    }

    public function estActive(): bool
    {
        return (bool) config('mfa.enabled', true);
    }

    public function estObligatoire(User $user): bool
    {
        if (! $this->estActive()) {
            return false;
        }

        $roles = config('mfa.required_roles', []);

        return $roles !== [] && $user->hasAnyRole($roles);
    }

    public function aMfaActive(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && filled($user->two_factor_secret);
    }

    public function genererSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /** @return array<int, string> Codes en clair (à afficher une seule fois). */
    public function genererCodesSecours(): array
    {
        $codes = [];
        for ($i = 0; $i < config('mfa.recovery_codes_count', 8); $i++) {
            $codes[] = strtoupper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }

    /** @param array<int, string> $codes */
    public function hasherCodesSecours(array $codes): array
    {
        return array_map(fn (string $code) => Hash::make(strtoupper($code)), $codes);
    }

    public function qrDataUri(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        $result = (new Builder(
            writer: new PngWriter(),
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 200,
            margin: 8,
        ))->build();

        return $result->getDataUri();
    }

    public function verifierTotp(User $user, string $code): bool
    {
        if (! filled($user->two_factor_secret)) {
            return false;
        }

        $code = $this->normaliserCodeTotp($code);

        return $this->estCodeDemoValide($code)
            || $this->google2fa->verifyKey($user->two_factor_secret, $code);
    }

    public function verifierTotpAvecSecret(string $secret, string $code): bool
    {
        $code = $this->normaliserCodeTotp($code);

        return $this->estCodeDemoValide($code)
            || $this->google2fa->verifyKey($secret, $code);
    }

    private function normaliserCodeTotp(string $code): string
    {
        return preg_replace('/\s+/', '', $code) ?: '';
    }

    private function estCodeDemoValide(string $code): bool
    {
        $codeDemo = (string) config('mfa.demo_code', '');

        return $codeDemo !== '' && hash_equals($codeDemo, $code);
    }

    /** Consomme un code de secours valide. */
    public function verifierCodeSecours(User $user, string $code): bool
    {
        $normalise = strtoupper(str_replace(' ', '', trim($code)));
        $hashes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashes as $i => $hash) {
            if (! Hash::check($normalise, $hash)) {
                continue;
            }

            unset($hashes[$i]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

            return true;
        }

        return false;
    }

    /** @param array<int, string> $codesSecoursClair */
    public function activer(User $user, string $secret, array $codesSecoursClair): void
    {
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->hasherCodesSecours($codesSecoursClair),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    public function desactiver(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
