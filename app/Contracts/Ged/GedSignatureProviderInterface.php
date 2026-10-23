<?php

namespace App\Contracts\Ged;

use App\Models\Ged\GedDocument;
use App\Models\Ged\GedSignature;
use App\Models\User;

/** Contrat extensible pour signature électronique (interne ou prestataire eIDAS). */
interface GedSignatureProviderInterface
{
    public function demander(GedDocument $document, User $demandeur, User $signataire): GedSignature;

    public function signer(GedSignature $signature, User $signataire): GedSignature;

    public function rejeter(GedSignature $signature, User $signataire, string $motif): GedSignature;

    /** @return array{integrite: bool, est_signe: bool, signatures: mixed, provider?: string, niveau?: string, qualification_eidas?: bool, prestataire_verifie?: bool} */
    public function verifier(GedDocument $document): array;
}
