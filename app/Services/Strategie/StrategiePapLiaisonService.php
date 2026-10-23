<?php

namespace App\Services\Strategie;

use App\Models\Activite;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Models\Strategie\ProjetStrategique;
use App\Services\Pap\PapPerformanceService;
use Illuminate\Support\Collection;

class StrategiePapLiaisonService
{
    public function __construct(private readonly PapPerformanceService $papPerformance)
    {
    }

    public function papPourExercice(int $exerciceId): ?Pap
    {
        return Pap::where('exercice_id', $exerciceId)->first();
    }

    public function lierProgrammation(ProgrammationAnnuelle $programmation, ?int $papId = null): void
    {
        $papId ??= $this->papPourExercice($programmation->exercice_id)?->id;

        $programmation->update([
            'pap_id' => $papId,
            'updated_by' => auth()->id(),
        ]);
    }

    /** Rattache automatiquement les programmations sans PAP pour un exercice. */
    public function synchroniserLiaisons(int $exerciceId): int
    {
        $pap = $this->papPourExercice($exerciceId);
        if (! $pap) {
            return 0;
        }

        return ProgrammationAnnuelle::where('exercice_id', $exerciceId)
            ->where(function ($q) use ($pap) {
                $q->whereNull('pap_id')->orWhere('pap_id', '!=', $pap->id);
            })
            ->update(['pap_id' => $pap->id, 'updated_by' => auth()->id()]);
    }

    /** @return array<string, mixed> */
    public function reconciliation(?int $exerciceId = null): array
    {
        $exerciceId ??= \App\Models\Exercice::firstWhere('statut', 'ouvert')?->id
            ?? \App\Models\Exercice::latest('annee')->value('id');

        $pap = $exerciceId ? $this->papPourExercice($exerciceId) : null;
        $programmations = $exerciceId
            ? ProgrammationAnnuelle::with('programmationPluriannuelle.planStrategique', 'pap')
                ->where('exercice_id', $exerciceId)
                ->get()
            : collect();

        $executionPap = $exerciceId ? $this->papPerformance->executionPap($exerciceId) : [];
        $budgetProjets = (float) ProjetStrategique::sum('budget_previsionnel');
        $piliers = $exerciceId ? Pilier::where('exercice_id', $exerciceId)->count() : 0;
        $piliersOrientes = $exerciceId
            ? Pilier::where('exercice_id', $exerciceId)->whereNotNull('orientation_strategique_id')->count()
            : 0;
        $activites = $exerciceId
            ? Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->count()
            : 0;
        $activitesProjet = $exerciceId
            ? Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
                ->whereNotNull('projet_id')->count()
            : 0;

        $liees = $programmations->whereNotNull('pap_id')->count();
        $totalProg = $programmations->count();

        return [
            'exercice_id' => $exerciceId,
            'pap' => $pap,
            'programmations' => $programmations,
            'programmations_liees' => $liees,
            'programmations_total' => $totalProg,
            'taux_liaison_prog' => $totalProg > 0 ? round($liees / $totalProg * 100, 1) : 0.0,
            'execution_pap' => $executionPap,
            'budget_projets_strategiques' => $budgetProjets,
            'ecart_budget_pap' => isset($executionPap['montant_prevu'])
                ? round($budgetProjets - (float) $executionPap['montant_prevu'], 2)
                : null,
            'piliers_orientes_pct' => $piliers > 0 ? round($piliersOrientes / $piliers * 100, 1) : 0.0,
            'activites_projet_pct' => $activites > 0 ? round($activitesProjet / $activites * 100, 1) : 0.0,
        ];
    }

    /** @return Collection<int, ProgrammationAnnuelle> */
    public function programmationsSansPap(): Collection
    {
        return ProgrammationAnnuelle::with('exercice', 'programmationPluriannuelle')
            ->whereNull('pap_id')
            ->orderByDesc('exercice_id')
            ->get();
    }
}
