<?php

namespace App\Services\Strategie;

use App\Models\Exercice;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use Illuminate\Support\Collection;

class StrategiePilotageService
{
    public function __construct(
        private readonly StrategieReferentielService $referentiel,
        private readonly StrategieAlignementService $alignement,
        private readonly StrategieCoherenceService $coherence,
        private readonly StrategiePapLiaisonService $papLiaison,
    ) {
    }

    /** @return array<string, mixed> */
    public function tableauDecisionnel(?int $exerciceId = null): array
    {
        $exerciceId ??= Exercice::firstWhere('statut', 'ouvert')?->id
            ?? Exercice::latest('annee')->value('id');

        $exercice = $exerciceId ? Exercice::find($exerciceId) : null;
        $hub = $this->referentiel->syntheseHub($this->alignement);
        $coherence = $this->coherence->rapport();
        $alignement = $this->alignement->syntheseAlignement();
        $reconciliation = $this->papLiaison->reconciliation($exerciceId);

        return [
            'exercice' => $exercice,
            'hub' => $hub,
            'coherence' => $coherence,
            'alignement' => $alignement,
            'reconciliation' => $reconciliation,
            'plans_actifs' => PlanStrategique::where('statut', 'actif')->orderBy('reference')->get(['id', 'reference', 'intitule']),
            'top_programmes' => $this->topProgrammes(),
            'top_projets' => $this->topProjets(),
            'alertes' => $this->alertes($reconciliation, $coherence, $alignement),
            'score_pilotage' => $this->scorePilotage($hub, $coherence, $alignement, $reconciliation),
        ];
    }

    /** @return Collection<int, ProgrammeStrategique> */
    private function topProgrammes(): Collection
    {
        return ProgrammeStrategique::with('orientationStrategique')
            ->orderByDesc('budget_previsionnel')
            ->limit(5)
            ->get(['id', 'code', 'intitule', 'budget_previsionnel', 'orientation_strategique_id']);
    }

    /** @return Collection<int, ProjetStrategique> */
    private function topProjets(): Collection
    {
        return ProjetStrategique::with('programmeStrategique')
            ->orderByDesc('budget_previsionnel')
            ->limit(5)
            ->get(['id', 'code', 'intitule', 'budget_previsionnel', 'budget_engage', 'programme_strategique_id']);
    }

    /** @param array<string, mixed> $reconciliation */
    /** @param array<string, mixed> $coherence */
    /** @param array<string, mixed> $alignement */
    /** @return list<string> */
    private function alertes(array $reconciliation, array $coherence, array $alignement): array
    {
        $alertes = [];

        if (($reconciliation['programmations_total'] ?? 0) > 0 && ($reconciliation['taux_liaison_prog'] ?? 0) < 100) {
            $alertes[] = 'Programmation(s) annuelle(s) non rattachée(s) au PAP.';
        }
        if (! ($reconciliation['pap'] ?? null)) {
            $alertes[] = 'Aucun PAP enregistré pour l\'exercice sélectionné.';
        }
        if ($coherence['objectifs_sans_indicateur']->isNotEmpty()) {
            $alertes[] = $coherence['objectifs_sans_indicateur']->count().' objectif(s) sans indicateur.';
        }
        if (($alignement['taux_matrice'] ?? 0) < 50) {
            $alertes[] = 'Taux d\'alignement matrice inférieur à 50 %.';
        }
        if (($reconciliation['activites_projet_pct'] ?? 0) < 30 && ($reconciliation['execution_pap']['activites'] ?? 0) > 0) {
            $alertes[] = 'Peu d\'activités PAP rattachées à un projet stratégique.';
        }

        return $alertes;
    }

    /** @param array<string, mixed> $hub */
    /** @param array<string, mixed> $coherence */
    /** @param array<string, mixed> $alignement */
    /** @param array<string, mixed> $reconciliation */
    private function scorePilotage(array $hub, array $coherence, array $alignement, array $reconciliation): float
    {
        $scores = [
            (float) ($hub['taux_alignement'] ?? 0),
            (float) ($coherence['score_global'] ?? 0),
            (float) ($alignement['taux_matrice'] ?? 0),
            (float) ($reconciliation['taux_liaison_prog'] ?? 0),
            (float) ($reconciliation['piliers_orientes_pct'] ?? 0),
            (float) ($reconciliation['activites_projet_pct'] ?? 0),
        ];

        return round(collect($scores)->avg(), 1);
    }
}
