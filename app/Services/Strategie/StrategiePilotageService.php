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
        $score = $this->scorePilotage($coherence, $alignement, $reconciliation);

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
            'score_pilotage' => $score['score'],
            'score_pilotage_evaluable' => $score['evaluable'],
            'score_pilotage_detail' => $score['composantes'],
            // Référentiel stratégique non renseigné : la page guide la saisie au lieu d'afficher des taux sans base.
            'referentiel_vide' => ($hub['plans_actifs'] ?? 0) + ($hub['programmes'] ?? 0) + ($hub['projets'] ?? 0) + ($hub['objectifs'] ?? 0) + ($hub['visions_actives'] ?? 0) === 0,
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

    /**
     * Alertes avec leur action corrective (lien vers l'écran où corriger).
     *
     * @return list<array{niveau: string, message: string, action: ?string, lien: ?string}>
     */
    private function alertes(array $reconciliation, array $coherence, array $alignement): array
    {
        $alertes = [];
        $ajouter = function (string $niveau, string $message, ?string $action = null, ?string $route = null, array $params = []) use (&$alertes) {
            $alertes[] = [
                'niveau' => $niveau, 'message' => $message, 'action' => $action,
                'lien' => $route ? route($route, $params, false) : null,
            ];
        };

        if (! ($reconciliation['pap'] ?? null)) {
            $ajouter('danger', 'Aucun PAP enregistré pour l\'exercice sélectionné.', 'Ouvrir les PAP', 'paps.index');
        }
        if (($reconciliation['programmations_total'] ?? 0) > 0 && ($reconciliation['taux_liaison_prog'] ?? 0) < 100) {
            $nonLiees = ($reconciliation['programmations_total'] ?? 0) - ($reconciliation['programmations_liees'] ?? 0);
            $ajouter('warning', "{$nonLiees} programmation(s) annuelle(s) non rattachée(s) au PAP.", 'Rattacher (pont PAP)', 'strategie.pont-pap', ['exercice' => $reconciliation['exercice_id'] ?? null]);
        }
        if ($coherence['objectifs_sans_indicateur']->isNotEmpty()) {
            $ajouter('warning', $coherence['objectifs_sans_indicateur']->count().' objectif(s) sans indicateur.', 'Compléter les indicateurs', 'strategie.indicateurs.create');
        }
        if ($coherence['indicateurs_sans_cible']->isNotEmpty()) {
            $ajouter('warning', $coherence['indicateurs_sans_cible']->count().' indicateur(s) sans cible.', 'Voir les indicateurs', 'strategie.indicateurs.index');
        }
        if ($coherence['risques_sans_mitigation']->isNotEmpty()) {
            $ajouter('warning', $coherence['risques_sans_mitigation']->count().' risque(s) sans mesure de mitigation.', 'Voir les risques', 'strategie.risques.index');
        }
        if (($alignement['lignes_matrice'] ?? 0) > 0 && ($alignement['taux_matrice'] ?? 0) < 50) {
            $ajouter('warning', 'Taux d\'alignement de la matrice inférieur à 50 % ('.number_format($alignement['taux_matrice'], 1, ',', ' ').' %).', 'Ouvrir la matrice', 'strategie.matrice');
        }
        if (($alignement['piliers'] ?? 0) > 0 && ($alignement['piliers_orientes_pct'] ?? 0) < 100) {
            $ajouter('info', 'Piliers du PAP non rattachés à une orientation stratégique ('.number_format($alignement['piliers_orientes_pct'], 1, ',', ' ').' % orientés).', 'Pont PAP', 'strategie.pont-pap', ['exercice' => $reconciliation['exercice_id'] ?? null]);
        }
        if (($reconciliation['activites_projet_pct'] ?? 0) < 30 && ($reconciliation['execution_pap']['activites'] ?? 0) > 0) {
            $ajouter('info', 'Peu d\'activités PAP rattachées à un projet stratégique ('.number_format($reconciliation['activites_projet_pct'] ?? 0, 1, ',', ' ').' %).', 'Voir les projets', 'strategie.projets.index');
        }

        return $alertes;
    }

    /**
     * Score de pilotage : moyenne des seules composantes évaluables (une composante sans élément à mesurer
     * n'est ni à 0 % ni à 100 %). Le « taux d'alignement » du hub étant le taux de la matrice, il n'est compté qu'une fois.
     *
     * @return array{score: float, evaluable: bool, composantes: list<array{libelle: string, taux: ?float, base: string}>}
     */
    private function scorePilotage(array $coherence, array $alignement, array $reconciliation): array
    {
        $composantes = [
            ['libelle' => 'Cohérence du référentiel', 'taux' => ($coherence['score_evaluable'] ?? true) ? (float) $coherence['score_global'] : null, 'base' => 'objectifs, indicateurs, risques, matrice'],
            ['libelle' => 'Matrice d\'alignement complète', 'taux' => ($alignement['lignes_matrice'] ?? 0) > 0 ? (float) $alignement['taux_matrice'] : null, 'base' => ($alignement['lignes_matrice'] ?? 0).' ligne(s)'],
            ['libelle' => 'Programmations annuelles liées au PAP', 'taux' => ($reconciliation['programmations_total'] ?? 0) > 0 ? (float) $reconciliation['taux_liaison_prog'] : null, 'base' => ($reconciliation['programmations_total'] ?? 0).' programmation(s)'],
            ['libelle' => 'Piliers PAP orientés', 'taux' => ($alignement['piliers'] ?? 0) > 0 ? (float) ($reconciliation['piliers_orientes_pct'] ?? 0) : null, 'base' => ($alignement['piliers'] ?? 0).' pilier(s)'],
            ['libelle' => 'Activités PAP rattachées à un projet', 'taux' => ($alignement['activites'] ?? 0) > 0 ? (float) ($reconciliation['activites_projet_pct'] ?? 0) : null, 'base' => ($alignement['activites'] ?? 0).' activité(s)'],
        ];
        $evaluables = collect($composantes)->pluck('taux')->filter(fn ($t) => $t !== null);

        return [
            'score' => $evaluables->isEmpty() ? 0.0 : round($evaluables->avg(), 1),
            'evaluable' => $evaluables->isNotEmpty(),
            'composantes' => $composantes,
        ];
    }
}
