<?php

namespace App\Services\Reporting;

use App\Models\Rapport;
use App\Models\User;
use Illuminate\Support\Facades\Config;

/**
 * Signature électronique des rapports : scellement HMAC-SHA256 du contenu (hash du fichier)
 * avec la clé applicative. Non répudiable et vérifiable sans le fichier original.
 */
class SignatureService
{
    /** Calcule la signature HMAC d'un rapport (référence + hash du fichier + signataire). */
    public function signer(Rapport $rapport, User $signataire): string
    {
        $charge = implode('|', [$rapport->reference, $rapport->hash, $signataire->id, $rapport->qr_token]);

        return hash_hmac('sha256', $charge, $this->cle());
    }

    /** Vérifie qu'une signature correspond bien au rapport et au signataire enregistrés. */
    public function verifier(Rapport $rapport): bool
    {
        if (! $rapport->estSigne() || ! $rapport->signature || ! $rapport->signe_par) {
            return false;
        }
        $charge = implode('|', [$rapport->reference, $rapport->hash, $rapport->signe_par, $rapport->qr_token]);
        $attendue = hash_hmac('sha256', $charge, $this->cle());

        return hash_equals($attendue, $rapport->signature);
    }

    private function cle(): string
    {
        $key = (string) Config::get('app.key');

        return str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }
}
