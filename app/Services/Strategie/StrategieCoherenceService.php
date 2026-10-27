<?php

namespace App\Services\Strategie;

use App\Models\Strategie\IndicateurStrategique;
use App\Models\Strategie\ObjectifStrategique;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\RisqueStrategique;
use Illuminate\Support\Collection;

class StrategieCoherenceService
{
    /** @return array<string, mixed> */
    public function rapport(?int $planStrategiqueId = null): array
    {
        $plansQuery = PlanStrategique::query()->withCount(['orientations', 'objectifs', 'risques']);
        if ($planStrategiqueId) {
            $plansQuery->where('id', $planStrategiqueId);
        }
        $plans = $plansQuery->orderBy('reference')->get();

        $objectifsQuery = ObjectifStrategique::query()->withCount('indicateurs');
        if ($planStrategiqueId) {
            $objectifsQuery->where('plan_strategique_id', $planStrategiqueId);
        }
        $objectifs = $objectifsQuery->orderBy('ordre')->get();

        $objectifsSansIndicateur = $objectifs->where('indicateurs_count', 0);
        $indicateursSansCible = $this->indicateursSansCible($planStrategiqueId);
        $risquesSansMitigation = $this->risquesSansMitigation($planStrategiqueId);
        $programmesSansBudget = $this->programmesSansBudget($planStrategiqueId);
        $plansEnAttente = $plans->where('statut', 'en_validation');
        $plansActifsNonValides = $plans->where('statut', 'actif')->whereNull('validated_at');

        $alignement = app(StrategieAlignementService::class)->syntheseAlignement();

        // Contrôles évaluables seulement : un contrôle sans élément à contrôler (aucun objectif, indicateur ou
        // risque) comptait pour 100 % et affichait 75 % de cohérence sur un référentiel vide.
        $controles = [
            ['libelle' => "Objectifs dotés d'au moins un indicateur", 'base' => $objectifs->count(), 'anomalies' => $objectifsSansIndicateur->count()],
            ['libelle' => "Indicateurs dotés d'une cible", 'base' => $this->countIndicateurs($planStrategiqueId), 'anomalies' => $indicateursSansCible->count()],
            ['libelle' => "Risques dotés d'une mesure de mitigation", 'base' => $this->countRisques($planStrategiqueId), 'anomalies' => $risquesSansMitigation->count()],
            ['libelle' => "Lignes de la matrice d'alignement complètes", 'base' => (int) ($alignement['lignes_matrice'] ?? 0), 'taux' => (float) $alignement['taux_matrice']],
        ];
        foreach ($controles as &$c) {
            $c['taux'] = $c['base'] === 0 ? null : ($c['taux'] ?? $this->pctOk($c['base'], $c['anomalies']));
        }
        unset($c);
        $scores = collect($controles)->pluck('taux')->filter(fn ($t) => $t !== null)->values()->all();

        return [
            'plan_id' => $planStrategiqueId,
            'plans' => $plans,
            'score_global' => $scores === [] ? 0.0 : round(collect($scores)->avg(), 1),
            'score_evaluable' => $scores !== [],
            'controles' => $controles,
            'objectifs_total' => $objectifs->count(),
            'objectifs_sans_indicateur' => $objectifsSansIndicateur,
            'indicateurs_sans_cible' => $indicateursSansCible,
            'risques_sans_mitigation' => $risquesSansMitigation,
            'programmes_sans_budget' => $programmesSansBudget,
            'plans_en_validation' => $plansEnAttente,
            'plans_actifs_non_valides' => $plansActifsNonValides,
            'alignement' => $alignement,
        ];
    }

    /** @return Collection<int, IndicateurStrategique> */
    private function indicateursSansCible(?int $planId): Collection
    {
        return IndicateurStrategique::query()
            ->whereNull('valeur_cible')
            ->when($planId, fn ($q) => $q->whereHas(
                'objectifStrategique',
                fn ($o) => $o->where('plan_strategique_id', $planId)
            ))
            ->with('objectifStrategique.planStrategique')
            ->orderBy('code')
            ->get();
    }

    /** @return Collection<int, RisqueStrategique> */
    private function risquesSansMitigation(?int $planId): Collection
    {
        return RisqueStrategique::query()
            ->where(function ($q) {
                $q->whereNull('mitigation')->orWhere('mitigation', '');
            })
            ->when($planId, fn ($q) => $q->where('plan_strategique_id', $planId))
            ->with('planStrategique')
            ->orderBy('code')
            ->get();
    }

    /** @return Collection<int, ProgrammeStrategique> */
    private function programmesSansBudget(?int $planId): Collection
    {
        return ProgrammeStrategique::query()
            ->where(function ($q) {
                $q->whereNull('budget_previsionnel')->orWhere('budget_previsionnel', '<=', 0);
            })
            ->when($planId, fn ($q) => $q->whereHas(
                'orientationStrategique',
                fn ($o) => $o->where('plan_strategique_id', $planId)
            ))
            ->with('orientationStrategique.planStrategique')
            ->orderBy('code')
            ->get();
    }

    private function countIndicateurs(?int $planId): int
    {
        return IndicateurStrategique::query()
            ->when($planId, fn ($q) => $q->whereHas(
                'objectifStrategique',
                fn ($o) => $o->where('plan_strategique_id', $planId)
            ))
            ->count();
    }

    private function countRisques(?int $planId): int
    {
        return RisqueStrategique::query()
            ->when($planId, fn ($q) => $q->where('plan_strategique_id', $planId))
            ->count();
    }

    private function pctOk(int $total, int $anomalies): float
    {
        if ($total === 0) {
            return 100.0;
        }

        return round((($total - $anomalies) / $total) * 100, 1);
    }
}
