<?php

namespace App\Services\Ged\Providers;

use App\Contracts\Ged\GedSignatureProviderInterface;
use App\Enums\Ged\DocumentStatut;
use App\Enums\Ged\SignatureNiveau;
use App\Enums\Ged\SignatureStatut;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedSignature;
use App\Models\User;
use App\Services\Ged\GedJournalService;
use App\Services\Ged\GedStorageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Prestataire de signature externe qualifiée (eIDAS / QTSP).
 * API REST configurable via GED_SIGNATURE_API_URL / GED_SIGNATURE_API_KEY.
 */
class ExternalSignatureProvider implements GedSignatureProviderInterface
{
    public function __construct(
        private GedJournalService $journal,
        private GedStorageService $storage,
        private InternalSignatureProvider $fallbackInterne,
    ) {}

    public function demander(GedDocument $document, User $demandeur, User $signataire): GedSignature
    {
        $url = config('ged.signature.externe.url');
        $niveau = config('ged.signature.externe.niveau', SignatureNiveau::Qualifiee->value);

        if (! $url) {
            return $this->fallbackInterne->demander($document, $demandeur, $signataire);
        }

        try {
            $response = Http::withToken(config('ged.signature.externe.api_key', ''))
                ->timeout(15)
                ->post(rtrim($url, '/').'/demandes', [
                    'document_uuid' => $document->uuid,
                    'hash' => $document->hash_sha256,
                    'signataire_email' => $signataire->email,
                    'demandeur_email' => $demandeur->email,
                    'niveau' => $niveau,
                ]);

            if ($response->successful()) {
                $externeId = (string) $response->json('id');

                $signature = GedSignature::create([
                    'document_id' => $document->id,
                    'signataire_id' => $signataire->id,
                    'statut' => 'en_attente',
                    'provider' => 'externe',
                    'niveau' => $niveau,
                    'externe_id' => $externeId,
                    'hash_document' => $document->hash_sha256,
                    'date_demande' => now(),
                    'metadonnees_prestataire' => $response->json(),
                ]);

                $document->update(['signature_status' => SignatureStatut::EnAttente]);
                $this->journal->enregistrer('demande_signature_externe', $document, $demandeur, null, [
                    'externe_id' => $externeId,
                    'niveau' => $niveau,
                ]);

                return $signature;
            }
        } catch (\Throwable $e) {
            Log::warning('GED signature externe indisponible, fallback interne', ['error' => $e->getMessage()]);
        }

        return $this->fallbackInterne->demander($document, $demandeur, $signataire);
    }

    public function signer(GedSignature $signature, User $signataire): GedSignature
    {
        $url = config('ged.signature.externe.url');

        if (! $url) {
            return $this->fallbackInterne->signer($signature, $signataire);
        }

        $document = $signature->document;

        abort_unless(
            $this->storage->verifierIntegrite($document->chemin_fichier, $document->hash_sha256),
            422,
            'Intégrité du document compromise.'
        );

        try {
            $externeId = $signature->externe_id;

            $response = Http::withToken(config('ged.signature.externe.api_key', ''))
                ->timeout(30)
                ->post(rtrim($url, '/').'/signer', [
                    'demande_id' => $externeId,
                    'hash' => $document->hash_sha256,
                ]);

            if ($response->successful()) {
                $payload = $response->json();
                $certificat = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
                $niveau = $payload['niveau'] ?? $signature->niveau ?? SignatureNiveau::Qualifiee->value;

                $signature->update([
                    'statut' => 'signe',
                    'provider' => 'externe',
                    'niveau' => $niveau,
                    'date_signature' => now(),
                    'horodatage' => now(),
                    'horodatage_tsa' => ! empty($payload['horodatage_tsa'])
                        ? Carbon::parse($payload['horodatage_tsa'])
                        : now(),
                    'empreinte_numerique' => $payload['empreinte'] ?? hash('sha256', $certificat),
                    'certificat_interne' => $certificat,
                    'token_verification' => $payload['token_verification'] ?? null,
                    'metadonnees_prestataire' => $payload,
                    'adresse_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                $document->update([
                    'est_signe' => true,
                    'est_verrouille' => true,
                    'statut' => DocumentStatut::Signe,
                    'signature_status' => SignatureStatut::Signe,
                    'updated_by' => $signataire->id,
                ]);

                $this->journal->enregistrer('signature_externe', $document, $signataire, null, [
                    'externe_id' => $externeId,
                    'niveau' => $niveau,
                ]);

                return $signature->fresh();
            }
        } catch (\Throwable $e) {
            Log::error('GED signature externe échouée', ['error' => $e->getMessage()]);
            throw new RuntimeException('Signature externe indisponible : '.$e->getMessage());
        }

        return $this->fallbackInterne->signer($signature, $signataire);
    }

    public function rejeter(GedSignature $signature, User $signataire, string $motif): GedSignature
    {
        return $this->fallbackInterne->rejeter($signature, $signataire, $motif);
    }

    public function verifier(GedDocument $document): array
    {
        $base = $this->fallbackInterne->verifier($document);

        if (config('ged.signature.provider', 'interne') !== 'externe') {
            return $base;
        }

        $signatures = GedSignature::query()
            ->where('document_id', $document->id)
            ->where('statut', 'signe')
            ->get();

        $niveauMax = $signatures->contains(fn (GedSignature $s) => $s->niveau === SignatureNiveau::Qualifiee->value)
            ? SignatureNiveau::Qualifiee->value
            : SignatureNiveau::Interne->value;

        $url = (string) (config('ged.signature.externe.url') ?? '');
        $prestataireVerifie = $url === '' || $signatures->where('provider', 'externe')->isEmpty();

        if ($url !== '') {
            $prestataireVerifie = true;
            foreach ($signatures as $signature) {
                if ($signature->provider !== 'externe' || ! $signature->externe_id) {
                    continue;
                }

                try {
                    $response = Http::withToken(config('ged.signature.externe.api_key', ''))
                        ->timeout(10)
                        ->get(rtrim($url, '/').'/verifier/'.$signature->externe_id);

                    if (! $response->successful()) {
                        $prestataireVerifie = false;
                    }
                } catch (\Throwable $e) {
                    Log::warning('GED vérification externe indisponible', ['error' => $e->getMessage()]);
                    $prestataireVerifie = false;
                }
            }
        }

        return array_merge($base, [
            'provider' => 'externe',
            'niveau' => $niveauMax,
            'qualification_eidas' => $niveauMax === SignatureNiveau::Qualifiee->value,
            'prestataire_verifie' => $prestataireVerifie,
        ]);
    }
}
