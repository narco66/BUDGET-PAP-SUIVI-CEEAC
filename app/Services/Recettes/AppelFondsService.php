<?php

namespace App\Services\Recettes;

use App\Models\AppelFonds;
use App\Models\Exercice;
use App\Models\User;
use RuntimeException;

/** Phase R4 — émission des appels de fonds et suivi du recouvrement. */
class AppelFondsService
{
    public function emettre(array $data, User $user): AppelFonds
    {
        $exercice = Exercice::findOrFail($data['exercice_id']);
        if ($exercice->estClos()) {
            throw new RuntimeException('Impossible d\'émettre un appel de fonds sur un exercice clos.');
        }

        return AppelFonds::create([
            'exercice_id' => $exercice->id,
            'etat_membre_id' => $data['etat_membre_id'] ?? null,
            'source_financement_id' => $data['source_financement_id'] ?? null,
            'contribution_id' => $data['contribution_id'] ?? null,
            'reference' => $data['reference'],
            'objet' => $data['objet'],
            'montant_appele' => $data['montant_appele'],
            'montant_regle' => 0,
            'date_emission' => $data['date_emission'],
            'date_echeance' => $data['date_echeance'] ?? null,
            'taux_penalite' => $data['taux_penalite'] ?? 0,
            'statut' => 'emis',
            'observations' => $data['observations'] ?? null,
            'emis_par' => $user->id,
        ]);
    }

    /** Enregistre un règlement (recouvrement) partiel ou total et met à jour le statut. */
    public function enregistrerRecouvrement(AppelFonds $appel, float $montant, ?string $observations = null): AppelFonds
    {
        if ($appel->statut === 'annule') {
            throw new RuntimeException('Appel de fonds annulé : aucun recouvrement possible.');
        }

        if ($montant <= 0) {
            throw new RuntimeException('Le montant du recouvrement doit être positif.');
        }

        $nouveauRegle = (float) $appel->montant_regle + $montant;
        if ($nouveauRegle > (float) $appel->montant_appele + 0.009) {
            throw new RuntimeException('Le règlement cumulé dépasse le montant appelé.');
        }

        $appel->update([
            'montant_regle' => $nouveauRegle,
            'statut' => $this->statutPourMontants($nouveauRegle, (float) $appel->montant_appele),
            'observations' => $observations ?? $appel->observations,
        ]);

        return $appel->refresh();
    }

    public function annuler(AppelFonds $appel): AppelFonds
    {
        if ((float) $appel->montant_regle > 0) {
            throw new RuntimeException('Un appel de fonds partiellement recouvré ne peut pas être annulé.');
        }

        $appel->update(['statut' => 'annule']);

        return $appel->refresh();
    }

    private function statutPourMontants(float $regle, float $appele): string
    {
        if ($regle <= 0) {
            return 'emis';
        }

        return $regle + 0.009 >= $appele ? 'solde' : 'partiellement_recouvre';
    }
}
