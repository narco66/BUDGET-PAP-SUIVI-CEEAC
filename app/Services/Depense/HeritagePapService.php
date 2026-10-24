<?php

namespace App\Services\Depense;

use App\Models\Activite;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Support\Montant;

/** Instantané programmatique PAP (lecture seule) — pas de ressaisie. */
class HeritagePapService
{
    /** @return array<string, mixed>|null */
    public function capturer(?int $activiteId, ?int $tacheId = null): ?array
    {
        if (! $activiteId) {
            return null;
        }

        $activite = Activite::with([
            'structure',
            'ligneBudgetaire',
            'sousProduit.produit.axe.pilier',
            'produit.axe.pilier',
            'taches',
            'indicateurs',
        ])->find($activiteId);

        if (! $activite) {
            return null;
        }

        $produit = $activite->produit ?? $activite->sousProduit?->produit;
        $axe = $produit?->axe;
        $pilier = $axe?->pilier;
        $ligne = $activite->ligneBudgetaire;
        $tache = $tacheId ? $activite->taches->firstWhere('id', $tacheId) : null;

        return [
            'pilier' => $pilier ? ['id' => $pilier->id, 'code' => $pilier->code, 'intitule' => $pilier->intitule] : null,
            'axe' => $axe ? ['id' => $axe->id, 'code' => $axe->code, 'intitule' => $axe->intitule] : null,
            'produit' => $produit ? ['id' => $produit->id, 'code' => $produit->code, 'intitule' => $produit->intitule] : null,
            'sous_produit' => $activite->sousProduit ? [
                'id' => $activite->sousProduit->id,
                'code' => $activite->sousProduit->code,
                'intitule' => $activite->sousProduit->intitule,
            ] : null,
            'activite' => [
                'id' => $activite->id,
                'code' => $activite->code,
                'intitule' => $activite->intitule,
                'montant_total' => Montant::api($activite->montant_total),
            ],
            'tache' => $tache ? ['id' => $tache->id, 'code' => $tache->code, 'intitule' => $tache->intitule] : null,
            'unite_responsable' => $activite->structure ? [
                'id' => $activite->structure->id,
                'code' => $activite->structure->code,
                'libelle' => $activite->structure->libelle,
            ] : null,
            'periode' => [
                'trim_1' => (bool) $activite->trim_1,
                'trim_2' => (bool) $activite->trim_2,
                'trim_3' => (bool) $activite->trim_3,
                'trim_4' => (bool) $activite->trim_4,
            ],
            'indicateurs' => $activite->relationLoaded('indicateurs')
                ? $activite->indicateurs->map(fn ($i) => [
                    'id' => $i->id,
                    'code' => $i->code ?? null,
                    'intitule' => $i->intitule ?? $i->libelle ?? null,
                ])->values()->all()
                : [],
            'budget' => $this->budgetLigne($ligne),
            'capture_le' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function alerteCredits(?LigneBudgetaire $ligne, float $montantDemande): ?array
    {
        if (! $ligne) {
            return null;
        }

        $disponible = (float) $ligne->disponible;
        if ($montantDemande <= $disponible + 0.005) {
            return null;
        }

        return [
            'ligne_id' => $ligne->id,
            'ligne_code' => $ligne->code,
            'disponible' => Montant::api($disponible),
            'demande' => Montant::api($montantDemande),
            'deficit' => Montant::api($montantDemande - $disponible),
            'message' => 'Crédit disponible insuffisant au regard du montant estimé. L’engagement restera bloqué tant que le disponible réel n’aura pas été rétabli par une opération budgétaire régulière.',
        ];
    }

    public function figer(ExpressionBesoin $besoin): void
    {
        $heritage = $besoin->estPap()
            ? $this->capturer($besoin->activite_id, $besoin->tache_id)
            : null;

        $besoin->forceFill([
            'heritage_pap' => $heritage,
            'alerte_credits' => $this->alerteCredits($besoin->ligneBudgetaire, (float) $besoin->montant_estime),
        ])->save();
    }

    /** @return array<string, string>|null */
    private function budgetLigne(?LigneBudgetaire $ligne): ?array
    {
        if (! $ligne) {
            return null;
        }

        return [
            'ligne_id' => (string) $ligne->id,
            'code' => (string) $ligne->code,
            'initial' => Montant::api($ligne->montant_ouvert),
            'revise' => Montant::api($ligne->montant_revise ?? $ligne->montant_ouvert),
            'engage' => Montant::api($ligne->montant_engage),
            'execute' => Montant::api($ligne->montant_paye),
            'disponible' => Montant::api($ligne->disponible),
        ];
    }
}
