<?php

namespace App\Services\Ged\Providers;

use App\Contracts\Ged\GedSignatureProviderInterface;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedSignature;
use App\Models\User;
use RuntimeException;

/**
 * Stub prestataire externe eIDAS — prêt pour branchement API tierce.
 * Configurer GED_SIGNATURE_PROVIDER=externe et GED_SIGNATURE_API_URL.
 */
class ExternalSignatureProviderStub implements GedSignatureProviderInterface
{
    public function demander(GedDocument $document, User $demandeur, User $signataire): GedSignature
    {
        throw new RuntimeException(
            'Prestataire externe non configuré. Définir GED_SIGNATURE_API_URL ou utiliser GED_SIGNATURE_PROVIDER=interne.'
        );
    }

    public function signer(GedSignature $signature, User $signataire): GedSignature
    {
        throw new RuntimeException('Signature externe non implémentée — branchez votre API eIDAS ici.');
    }

    public function rejeter(GedSignature $signature, User $signataire, string $motif): GedSignature
    {
        throw new RuntimeException('Rejet signature externe non implémenté.');
    }

    public function verifier(GedDocument $document): array
    {
        return [
            'integrite' => false,
            'est_signe' => $document->est_signe,
            'signatures' => collect(),
            'provider' => 'externe',
            'message' => 'Vérification externe non configurée',
        ];
    }
}
