<?php

namespace Database\Seeders\Demo;

use App\Models\Activite;
use App\Models\CampagneSuivi;
use App\Models\Exercice;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuiviDemoSeeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            return;
        }

        $saisiPar = User::role('point_focal')->value('id') ?? User::role('chef_service')->value('id') ?? User::first()->id;
        $validePar = User::role('commissaire')->value('id') ?? User::role('chef_service')->value('id') ?? $saisiPar;

        $campagne = CampagneSuivi::firstOrCreate(
            ['exercice_id' => $exercice->id, 'periode' => '2026-T1'],
            [
                'libelle' => 'Campagne de suivi T1 2026',
                'type' => 'trimestriel',
                'date_debut' => $exercice->date_debut ?? now(),
                'date_fin' => $exercice->date_debut?->addMonths(3) ?? now()->addMonths(3),
                'statut' => 'ouverte',
            ]
        );

        $activites = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exercice->id))
            ->where('montant_total', '>', 0)
            ->inRandomOrder()
            ->limit(12)
            ->get();

        foreach ($activites as $activite) {
            $tauxPhysique = random_int(20, 100);
            $montantRealise = round((float) $activite->montant_total * $tauxPhysique / 100, 2);
            $status = $tauxPhysique >= 75 ? 'validee' : ($tauxPhysique >= 50 ? 'verifiee' : 'saisie');

            $collecte = $activite->collectes()->firstOrNew(['campagne_id' => $campagne->id]);
            $collecte->fill([
                'taux_physique' => $tauxPhysique,
                'montant_realise' => $montantRealise,
                'commentaire' => 'Collecte de démonstration pour l’activité '.$activite->code,
                'statut' => $status,
                'saisi_par' => $saisiPar,
                'verifie_par' => $status !== 'saisie' ? $validePar : null,
                'valide_par' => $status === 'validee' ? $validePar : null,
            ]);
            $collecte->recalculer();
            $collecte->save();

            if ($status === 'validee') {
                $collecte->appliquerSurActivite();
            }
        }
    }
}
