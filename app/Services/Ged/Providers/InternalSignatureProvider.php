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

/** Signature électronique interne CEEAC (certificat + horodatage). */
class InternalSignatureProvider implements GedSignatureProviderInterface
{
    public function __construct(
        private GedJournalService $journal,
        private GedStorageService $storage,
    ) {}

    public function demander(GedDocument $document, User $demandeur, User $signataire): GedSignature
    {
        $signature = GedSignature::create([
            'document_id' => $document->id,
            'signataire_id' => $signataire->id,
            'statut' => 'en_attente',
            'provider' => 'interne',
            'niveau' => SignatureNiveau::Interne->value,
            'hash_document' => $document->hash_sha256,
            'date_demande' => now(),
        ]);

        $document->update(['signature_status' => SignatureStatut::EnAttente]);

        $this->journal->enregistrer('demande_signature', $document, $demandeur, null, [
            'signataire_id' => $signataire->id,
            'provider' => 'interne',
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

        $certificat = $this->genererCertificatInterne($document, $signataire);

        DB::transaction(function () use ($signature, $signataire, $document, $certificat) {
            $signature->update([
                'statut' => 'signe',
                'provider' => 'interne',
                'niveau' => SignatureNiveau::Interne->value,
                'date_signature' => now(),
                'horodatage' => now(),
                'empreinte_numerique' => hash('sha256', $certificat),
                'certificat_interne' => $certificat,
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
        });

        $this->journal->enregistrer('signature', $document, $signataire, null, ['provider' => 'interne']);

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
        return [
            'integrite' => $this->storage->verifierIntegrite($document->chemin_fichier, $document->hash_sha256),
            'est_signe' => $document->est_signe,
            'signatures' => $document->signatures()->where('statut', 'signe')->with('signataire')->get(),
            'provider' => 'interne',
            'niveau' => SignatureNiveau::Interne->value,
            'qualification_eidas' => false,
            'prestataire_verifie' => false,
        ];
    }

    private function genererCertificatInterne(GedDocument $document, User $signataire): string
    {
        $payload = json_encode([
            'document_uuid' => $document->uuid,
            'reference' => $document->reference,
            'hash' => $document->hash_sha256,
            'signataire' => $signataire->email,
            'date' => now()->toIso8601String(),
            'provider' => 'interne',
        ], JSON_THROW_ON_ERROR);

        return base64_encode($payload);
    }
}
