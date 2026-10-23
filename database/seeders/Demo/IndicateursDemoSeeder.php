<?php

namespace Database\Seeders\Demo;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\Pilier;
use Illuminate\Database\Seeder;

/** Indicateurs (stratégiques par pilier, opérationnels par activité) + mesures T1 — non destructif. */
class IndicateursDemoSeeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            return;
        }

        $taux = [78, 62, 45, 88, 30, 55, 70, 40, 95, 50]; // valeurs déterministes
        $i = 0;

        foreach (Pilier::where('exercice_id', $exercice->id)->orderBy('code')->get() as $pilier) {
            $ind = Indicateur::firstOrCreate(
                ['code' => 'IND-'.$pilier->code],
                [
                    'rbm_type' => Pilier::class, 'rbm_id' => $pilier->id,
                    'libelle' => 'Taux de mise en œuvre — '.$pilier->intitule,
                    'type' => 'strategique', 'unite' => '%', 'methode_calcul' => 'Activités réalisées / planifiées',
                    'source_verification' => 'Rapports de suivi trimestriels', 'periodicite' => 'annuel',
                    'valeur_reference' => 0, 'valeur_cible' => 100, 'seuil_alerte' => 50,
                ]
            );
            $this->mesure($ind, $taux[$i % count($taux)]);
            $i++;
        }

        foreach (Activite::query()->whereNotNull('sous_produit_id')->orderBy('code')->limit(6)->get() as $act) {
            $ind = Indicateur::firstOrCreate(
                ['code' => 'IND-'.$act->code],
                [
                    'rbm_type' => Activite::class, 'rbm_id' => $act->id,
                    'libelle' => "Niveau d'exécution — ".$act->code,
                    'type' => 'operationnel', 'unite' => '%', 'methode_calcul' => 'Avancement physique',
                    'source_verification' => 'Fiche de collecte', 'periodicite' => 'trimestriel',
                    'valeur_reference' => 0, 'valeur_cible' => 100, 'seuil_alerte' => 50,
                ]
            );
            $this->mesure($ind, $taux[$i % count($taux)]);
            $i++;
        }
    }

    private function mesure(Indicateur $ind, float $valeur): void
    {
        $ind->mesures()->updateOrCreate(
            ['periode' => '2026-T1'],
            [
                'valeur_realisee' => $valeur,
                'taux_atteinte' => $valeur, // cible = 100
                'feu' => $valeur >= 75 ? 'vert' : ($valeur >= 50 ? 'orange' : 'rouge'),
                'commentaire' => 'Mesure de démonstration (1er trimestre 2026).',
                'statut' => 'validee',
            ]
        );
    }
}
