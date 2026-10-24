<?php

namespace App\Services\Depense;

use App\Models\ChaineDepenseParametre;
use App\Models\Ordonnancement;
use App\Support\Montant;

/** Seuil de compétence ORD — paramètre versionné, non rétroactif. */
class SeuilOrdonnancementService
{
    /** @return array{montant:string,version:int,assiette:string,juridiquement_valide:bool,ordonnateur_role:string,circuit:string} */
    public function snapshotPour(float $montantNet): array
    {
        $param = ChaineDepenseParametre::query()->where('cle', 'ordonnancement.seuil_xaf')->first();
        $assiette = ChaineDepenseParametre::query()->where('cle', 'ordonnancement.assiette')->value('valeur')
            ?: (string) config('chaine_depense.ordonnancement.assiette', 'net');

        $seuil = $param?->valeur ?? (string) config('chaine_depense.ordonnancement.seuil_xaf', '5000000.00');
        $version = (int) ($param?->version ?? 1);
        $valide = (bool) ($param?->juridiquement_valide ?? config('chaine_depense.ordonnancement.juridiquement_valide'));

        $president = $montantNet - (float) $seuil > 0.005;
        $role = $president ? 'president' : 'secretaire_general';

        return [
            'montant' => Montant::api($seuil),
            'version' => $version,
            'assiette' => $assiette,
            'juridiquement_valide' => $valide,
            'ordonnateur_role' => $role,
            'circuit' => $president ? 'ORDONNANCEMENT_PRESIDENT' : 'ORDONNANCEMENT',
        ];
    }

    public function appliquer(Ordonnancement $ordonnancement): array
    {
        $snap = $this->snapshotPour((float) $ordonnancement->montant);
        $ordonnancement->forceFill([
            'seuil_xaf' => $snap['montant'],
            'seuil_version' => $snap['version'],
            'assiette_seuil' => $snap['assiette'],
            'ordonnateur_role' => $snap['ordonnateur_role'],
        ])->save();

        return $snap;
    }

    public function circuit(Ordonnancement $ordonnancement): string
    {
        if ($ordonnancement->ordonnateur_role) {
            return $ordonnancement->ordonnateur_role === 'president'
                ? 'ORDONNANCEMENT_PRESIDENT'
                : 'ORDONNANCEMENT';
        }

        return $this->appliquer($ordonnancement)['circuit'];
    }
}
