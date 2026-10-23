<?php



namespace App\Services\Ged;



use App\Contracts\Ged\GedSignatureProviderInterface;

use App\Enums\Ged\SignatureNiveau;

use App\Models\Ged\GedDocument;

use App\Models\Ged\GedSignature;

use App\Models\User;



/** Façade signature — délègue au provider configuré (interne / externe eIDAS / test). */

class GedSignatureService

{

    public function __construct(

        private GedSignatureProviderInterface $provider,

        private GedNotificationService $notifications,

    ) {}



    public function demander(GedDocument $document, User $demandeur, User $signataire): GedSignature

    {

        $signature = $this->provider->demander($document, $demandeur, $signataire);

        $this->notifications->signatureDemandee($document, $signature);



        return $signature;

    }



    public function signer(GedSignature $signature, User $signataire): GedSignature

    {

        $signature = $this->provider->signer($signature, $signataire);

        $this->notifications->documentSigne($signature->document);



        return $signature;

    }



    public function rejeter(GedSignature $signature, User $signataire, string $motif): GedSignature

    {

        return $this->provider->rejeter($signature, $signataire, $motif);

    }



    public function verifier(GedDocument $document): array

    {

        return $this->provider->verifier($document);

    }



    /** État pour l'administration GED / supervision (P3-6). */

    public function statut(): array

    {

        $cfg = config('ged.signature', []);

        $provider = (string) ($cfg['provider'] ?? 'interne');

        $niveauCfg = (string) ($cfg['externe']['niveau'] ?? SignatureNiveau::Qualifiee->value);

        $url = $cfg['externe']['url'] ?? null;



        $niveau = match ($provider) {

            'externe' => $niveauCfg,

            'test' => SignatureNiveau::Qualifiee->value,

            default => SignatureNiveau::Interne->value,

        };



        $prestataireDisponible = match ($provider) {

            'test' => true,

            'externe' => filled($url),

            default => true,

        };



        return [

            'provider' => $provider,

            'niveau' => $niveau,

            'niveau_libelle' => SignatureNiveau::tryFrom($niveau)?->libelle() ?? $niveau,

            'qualification_eidas' => in_array($provider, ['externe', 'test'], true),

            'api_url_configuree' => filled($url),

            'prestataire_disponible' => $prestataireDisponible,

        ];

    }

}

