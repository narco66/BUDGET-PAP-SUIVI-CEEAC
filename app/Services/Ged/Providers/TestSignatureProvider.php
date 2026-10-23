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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Driver test CI — simule une signature qualifiée eIDAS sans appel HTTP réel (P3-6).
 */
class TestSignatureProvider implements GedSignatureProviderInterface
{
    public const MARQUEUR_QUALIFIE = 'EIDAS-QUALIFIE-TEST';

    public function __construct(
        private GedJournalService $journal,
        private GedStorageService $storage,
    ) {}

    public function demander(GedDocument $document, User $demandeur, User $signataire): GedSignature
    {
        $externeId = 'TEST-'.Str::uuid()->toString();

        $signature = GedSignature::create([
            'document_id' => $document->id,
            'signataire_id' => $signataire->id,
            'statut' => 'en_attente',
            'provider' => 'test',
            'niveau' => SignatureNiveau::Qualifiee->value,
            'externe_id' => $externeId,
            'hash_document' => $document->hash_sha256,
            'date_demande' => now(),
            'metadonnees_prestataire' => [
                'simulation' => true,
                'niveau_eidas' => 'qualifiee',
                'prestataire' => 'TestSignatureProvider',
            ],
        ]);

        $document->update(['signature_status' => SignatureStatut::EnAttente]);

        $this->journal->enregistrer('demande_signature_qualifiee', $document, $demandeur, null, [
            'externe_id' => $externeId,
            'provider' => 'test',
            'niveau' => SignatureNiveau::Qualifiee->value,
        ]);

        return $signature;
    }

    public function signer(GedSignature $signature, User $signataire): GedSignature
    {
        abort_unless($signature->signataire_id === $signataire->id, 403);
        abort_unless($signature->statut === 'en_attente', 422);

        $document = $signature->document;

        abort_unless(
            $this->storage->verifierIntegrite($document->chemin_fichier, $document->hash_sha256),
            422,
            'Intégrité du document compromise.'
        );

        $certificat = $this->genererCertificatQualifie($document, $signataire, $signature);
        $token = hash('sha256', $signature->externe_id.$certificat);

        DB::transaction(function () use ($signature, $signataire, $document, $certificat, $token) {
            $signature->update([
                'statut' => 'signe',
                'date_signature' => now(),
                'horodatage' => now(),
                'horodatage_tsa' => now(),
                'empreinte_numerique' => hash('sha256', $certificat),
                'certificat_interne' => $certificat,
                'token_verification' => $token,
                'adresse_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadonnees_prestataire' => array_merge($signature->metadonnees_prestataire ?? [], [
                    'empreinte' => self::MARQUEUR_QUALIFIE,
                    'format' => 'PAdES-B-B',
                    'tsa' => 'TSA-TEST-CEEAC',
                ]),
            ]);

            $document->update([
                'est_signe' => true,
                'est_verrouille' => true,
                'statut' => DocumentStatut::Signe,
                'signature_status' => SignatureStatut::Signe,
                'updated_by' => $signataire->id,
            ]);
        });

        $this->journal->enregistrer('signature_qualifiee', $document, $signataire, null, [
            'provider' => 'test',
            'niveau' => SignatureNiveau::Qualifiee->value,
            'externe_id' => $signature->externe_id,
        ]);

        return $signature->fresh();
    }

    public function rejeter(GedSignature $signature, User $signataire, string $motif): GedSignature
    {
        abort_unless($signature->signataire_id === $signataire->id, 403);

        $signature->update([
            'statut' => 'rejete',
            'motif_rejet' => $motif,
            'date_signature' => now(),
        ]);

        $signature->document->update(['signature_status' => SignatureStatut::Rejete]);

        $this->journal->enregistrer('rejet_signature', $signature->document, $signataire, null, ['motif' => $motif]);

        return $signature->fresh();
    }

    public function verifier(GedDocument $document): array
    {
        $signatures = $document->signatures()->where('statut', 'signe')->with('signataire')->get();
        $qualifiee = $signatures->contains(fn (GedSignature $s) => $s->niveau === SignatureNiveau::Qualifiee->value);

        return [
            'integrite' => $this->storage->verifierIntegrite($document->chemin_fichier, $document->hash_sha256),
            'est_signe' => $document->est_signe,
            'signatures' => $signatures,
            'provider' => 'test',
            'niveau' => $qualifiee ? SignatureNiveau::Qualifiee->value : SignatureNiveau::Interne->value,
            'qualification_eidas' => $qualifiee,
            'prestataire_verifie' => true,
        ];
    }

    private function genererCertificatQualifie(GedDocument $document, User $signataire, GedSignature $signature): string
    {
        $payload = json_encode([
            'document_uuid' => $document->uuid,
            'reference' => $document->reference,
            'hash' => $document->hash_sha256,
            'signataire' => $signataire->email,
            'date' => now()->toIso8601String(),
            'provider' => 'test',
            'niveau' => SignatureNiveau::Qualifiee->value,
            'externe_id' => $signature->externe_id,
            'marqueur' => self::MARQUEUR_QUALIFIE,
            'eidas' => ['qualifiee' => true, 'format' => 'PAdES-B-B'],
        ], JSON_THROW_ON_ERROR);

        return base64_encode($payload);
    }
}
