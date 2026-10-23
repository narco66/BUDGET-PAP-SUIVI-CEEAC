<?php

namespace App\Services\Reporting;

use App\Models\Activite;
use App\Models\Engagement;
use App\Models\Indicateur;
use App\Models\Paiement;
use App\Models\Pilier;
use App\Services\Budget\SuiviFinancierCalculator;
use App\Services\Patrimoine\ImmobilisationService;
use App\Services\Pap\PapPerformanceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moteur de calcul du reporting institutionnel CEEAC.
 *
 * RÈGLE D'OR : tous les montants proviennent EXCLUSIVEMENT des compteurs réels de la chaîne de la
 * dépense (lignes_budgetaires : ouvert ≥ engagé ≥ liquidé ≥ ordonnancé ≥ payé) et de la chaîne
 * RBM/GAR. Chaque ventilation (axe, bailleur, catégorie) est RÉCONCILIÉE au global : la somme des
 * lignes + un éventuel reliquat « non ventilé » est strictement égale au total de l'exercice.
 */
class ReportingService
{
    /** Synthèse globale d'exécution (référence de réconciliation). */
    public function global(int $exerciceId): array
    {
        $t = DB::table('lignes_budgetaires')->where('exercice_id', $exerciceId)->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(montant_ouvert),0) ouvert,
                         COALESCE(SUM(COALESCE(montant_revise, montant_ouvert)),0) alloue,
                         COALESCE(SUM(montant_engage),0) engage,
                         COALESCE(SUM(montant_liquide),0) liquide, COALESCE(SUM(montant_ordonnance),0) ordonnance,
                         COALESCE(SUM(montant_paye),0) paye')
            ->first();

        return $this->ligneSynthese(
            (float) $t->ouvert,
            (float) $t->engage,
            (float) $t->liquide,
            (float) $t->ordonnance,
            (float) $t->paye,
            (float) $t->alloue,
        );
    }

    /** Exécution budgétaire par AXE stratégique (lignes rattachées à une activité du PAP). */
    public function parAxe(int $exerciceId): Collection
    {
        $axes = DB::table('lignes_budgetaires as lb')
            ->join('activites as a', function ($j) {
                $j->on('lb.activite_id', '=', 'a.id')->whereNull('a.deleted_at');
            })
            ->join('sous_produits as sp', 'a.sous_produit_id', '=', 'sp.id')
            ->join('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as pil', 'ax.pilier_id', '=', 'pil.id')
            ->where('lb.exercice_id', $exerciceId)->whereNull('lb.deleted_at')
            ->groupBy('ax.id', 'ax.code', 'ax.intitule', 'pil.code')
            ->selectRaw('pil.code as pilier, ax.code, ax.intitule,
                         COALESCE(SUM(lb.montant_ouvert),0) ouvert,
                         COALESCE(SUM(COALESCE(lb.montant_revise, lb.montant_ouvert)),0) alloue,
                         COALESCE(SUM(lb.montant_engage),0) engage,
                         COALESCE(SUM(lb.montant_liquide),0) liquide, COALESCE(SUM(lb.montant_ordonnance),0) ordonnance,
                         COALESCE(SUM(lb.montant_paye),0) paye')
            ->orderBy('pil.code')->orderBy('ax.code')->get()
            ->map(fn ($r) => array_merge(
                ['pilier' => $r->pilier, 'code' => $r->code, 'intitule' => $r->intitule],
                $this->ligneSynthese(
                    (float) $r->ouvert,
                    (float) $r->engage,
                    (float) $r->liquide,
                    (float) $r->ordonnance,
                    (float) $r->paye,
                    (float) $r->alloue,
                )
            ));

        return $this->avecReliquat($axes, $this->global($exerciceId), 'Hors PAP (fonctionnement & charges communes)', ['pilier' => '—', 'code' => '—']);
    }

    /** Exécution par BAILLEUR / source de financement, avec comparaison CEEAC vs PTF. */
    public function parBailleur(int $exerciceId): array
    {
        $bucket = $this->ventilerMontantsParSource($exerciceId);

        $meta = DB::table('sources_financement as s')
            ->leftJoin('partenaires_financiers as p', 'p.id', '=', 's.partenaire_id')
            ->get(['s.id', 's.libelle', 's.type', 's.bailleur', 'p.libelle as partenaire', 'p.sigle'])
            ->keyBy('id');

        $sources = collect($bucket)->map(function ($m, $sourceId) use ($meta) {
            $info = $meta->get($sourceId);

            return array_merge([
                'libelle' => $info->libelle ?? 'Non spécifié',
                'type' => $info->type ?? 'non_specifie',
                'bailleur' => $info->partenaire ?? $info->bailleur ?? null,
                'partenaire' => $info->sigle ?? $info->partenaire ?? null,
            ], $this->ligneSynthese(
                $m['ouvert'],
                $m['engage'],
                $m['liquide'],
                $m['ordonnance'],
                $m['paye'],
                $m['alloue'] ?? $m['ouvert'],
            ));
        })->sortByDesc('ouvert')->values();

        $synthese = collect(['interne_ceeac' => 'Financement CEEAC', 'externe_ptf' => 'Partenaires (PTF)', 'non_specifie' => 'Non spécifié'])
            ->map(function ($label, $type) use ($sources) {
                $grp = $sources->where('type', $type);

                return array_merge(['type' => $label], $this->ligneSynthese(
                    $grp->sum('ouvert'),
                    $grp->sum('engage'),
                    $grp->sum('liquide'),
                    $grp->sum('ordonnance'),
                    $grp->sum('paye'),
                    $grp->sum(fn ($r) => $r['alloue'] ?? $r['ouvert']),
                ));
            })->filter(fn ($r) => $r['ouvert'] > 0 || $r['engage'] > 0)->values();

        return ['sources' => $sources, 'ceeac_ptf' => $synthese];
    }

    /** Analyse financière structurée par partenaire PTF. */
    public function parPartenaire(int $exerciceId): Collection
    {
        $bucket = $this->ventilerMontantsParSource($exerciceId);
        $sources = DB::table('sources_financement')->get(['id', 'partenaire_id', 'libelle'])->keyBy('id');
        $partenaires = DB::table('partenaires_financiers')->get(['id', 'code', 'libelle', 'sigle'])->keyBy('id');

        $agg = [];
        foreach ($bucket as $sourceId => $m) {
            $pid = $sources->get($sourceId)?->partenaire_id ?? 0;
            if (! isset($agg[$pid])) {
                $p = $partenaires->get($pid);
                $agg[$pid] = [
                    'code' => $p->code ?? '—',
                    'libelle' => $p->libelle ?? 'Non rattaché PTF',
                    'sigle' => $p->sigle ?? null,
                    'ouvert' => 0, 'alloue' => 0, 'engage' => 0, 'liquide' => 0, 'ordonnance' => 0, 'paye' => 0,
                ];
            }
            foreach (['ouvert', 'alloue', 'engage', 'liquide', 'ordonnance', 'paye'] as $k) {
                $agg[$pid][$k] += $m[$k] ?? 0;
            }
        }

        return collect($agg)->map(fn ($r) => array_merge($r, $this->ligneSynthese(
            $r['ouvert'], $r['engage'], $r['liquide'], $r['ordonnance'], $r['paye'], $r['alloue']
        )))->sortByDesc('ouvert')->values();
    }

    /** Comparaison multi-annuelle CEEAC vs PTF sur les N derniers exercices. */
    public function bailleurMultiAnnuel(int $limit = 4): Collection
    {
        return DB::table('exercices as e')
            ->leftJoin('lignes_budgetaires as lb', function ($j) {
                $j->on('lb.exercice_id', '=', 'e.id')->whereNull('lb.deleted_at');
            })
            ->leftJoin('sources_financement as s', 'lb.source_financement_id', '=', 's.id')
            ->groupBy('e.id', 'e.annee')
            ->selectRaw("e.annee,
                COALESCE(SUM(CASE WHEN s.type='interne_ceeac' THEN lb.montant_paye ELSE 0 END),0) ceeac,
                COALESCE(SUM(CASE WHEN s.type='externe_ptf' THEN lb.montant_paye ELSE 0 END),0) ptf,
                COALESCE(SUM(lb.montant_ouvert),0) ouvert, COALESCE(SUM(lb.montant_paye),0) paye")
            ->orderByDesc('e.annee')->limit($limit)->get()
            ->map(fn ($r) => [
                'annee' => (int) $r->annee, 'ceeac' => (float) $r->ceeac, 'ptf' => (float) $r->ptf,
                'ouvert' => (float) $r->ouvert, 'paye' => (float) $r->paye,
            ]);
    }

    /** Exécution par CATÉGORIE (nature budgétaire = Titre de la nomenclature). */
    public function parCategorie(int $exerciceId): Collection
    {
        $titres = DB::table('lignes_budgetaires as lb')
            ->join('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->join('budget_paragraphes as pa', 'r.paragraphe_id', '=', 'pa.id')
            ->join('budget_articles as a', 'pa.article_id', '=', 'a.id')
            ->join('budget_titres as t', 'a.titre_id', '=', 't.id')
            ->where('lb.exercice_id', $exerciceId)->whereNull('lb.deleted_at')->where('t.sens', 'depense')
            ->groupBy('t.id', 't.code', 't.intitule')
            ->selectRaw('t.code, t.intitule, COALESCE(SUM(lb.montant_ouvert),0) ouvert,
                         COALESCE(SUM(COALESCE(lb.montant_revise, lb.montant_ouvert)),0) alloue,
                         COALESCE(SUM(lb.montant_engage),0) engage,
                         COALESCE(SUM(lb.montant_liquide),0) liquide, COALESCE(SUM(lb.montant_ordonnance),0) ordonnance,
                         COALESCE(SUM(lb.montant_paye),0) paye')
            ->orderBy('t.code')->get()
            ->map(fn ($r) => array_merge(
                ['code' => $r->code, 'intitule' => $r->intitule],
                $this->ligneSynthese(
                    (float) $r->ouvert,
                    (float) $r->engage,
                    (float) $r->liquide,
                    (float) $r->ordonnance,
                    (float) $r->paye,
                    (float) $r->alloue,
                )
            ));

        return $this->avecReliquat($titres, $this->global($exerciceId), 'Autres natures', ['code' => '—']);
    }

    /** Détail d'une catégorie (drill-down par article/paragraphe). */
    public function categorieDetail(int $exerciceId, string $titreCode): Collection
    {
        return DB::table('lignes_budgetaires as lb')
            ->join('budget_rubriques as r', 'lb.rubrique_id', '=', 'r.id')
            ->join('budget_paragraphes as pa', 'r.paragraphe_id', '=', 'pa.id')
            ->join('budget_articles as a', 'pa.article_id', '=', 'a.id')
            ->join('budget_titres as t', 'a.titre_id', '=', 't.id')
            ->where('lb.exercice_id', $exerciceId)->whereNull('lb.deleted_at')->where('t.code', $titreCode)
            ->groupBy('a.id', 'a.code', 'a.intitule')
            ->selectRaw('a.code, a.intitule, COALESCE(SUM(lb.montant_ouvert),0) ouvert,
                         COALESCE(SUM(lb.montant_engage),0) engage, COALESCE(SUM(lb.montant_paye),0) paye')
            ->orderBy('a.code')->get()
            ->map(fn ($r) => array_merge(['code' => $r->code, 'intitule' => $r->intitule],
                $this->ligneSynthese((float) $r->ouvert, (float) $r->engage, 0, 0, (float) $r->paye)));
    }

    /**
     * Arbre technico-financier RBM/GAR (Pilier→Activité) aplati : tous les IOV par activité,
     * taux financier réel via PapPerformanceService (ligne budgétaire payée).
     */
    public function rbmGar(int $exerciceId): Collection
    {
        $perf = app(PapPerformanceService::class);
        $lignes = collect();

        $piliers = Pilier::where('exercice_id', $exerciceId)
            ->with([
                'axes.produits.sousProduits.activites' => fn ($q) => $q->with([
                    'ligneBudgetaire',
                    'indicateurs.mesures',
                    'indicateurs.sourcesVerification',
                    'indicateurs.typeIndicateur',
                ]),
            ])
            ->orderBy('code')
            ->get();

        foreach ($piliers as $pilier) {
            $lignes->push($this->noeudRbm(0, $pilier->code, $pilier->intitule));
            foreach ($pilier->axes as $axe) {
                $lignes->push($this->noeudRbm(1, $axe->code, $axe->intitule));
                foreach ($axe->produits as $produit) {
                    $lignes->push($this->noeudRbm(2, $produit->code, $produit->intitule));
                    foreach ($produit->sousProduits as $sp) {
                        $lignes->push($this->noeudRbm(3, $sp->code, $sp->intitule));
                        foreach ($sp->activites as $act) {
                            $lignes = $lignes->merge($this->lignesActivite($act, $perf));
                        }
                    }
                }
            }
        }

        return $lignes;
    }

    /** Synthèse exécutive pour le rapport rbm_gar (alignée PapPerformanceService). */
    public function rbmGarSynthese(int $exerciceId): array
    {
        $exec = app(PapPerformanceService::class)->executionPap($exerciceId);
        $lignesAct = $this->rbmGar($exerciceId)->where('activite', true);

        return array_merge($exec, [
            'nb_lignes_activite' => $lignesAct->pluck('activite_id')->unique()->count(),
            'nb_iov' => $lignesAct->count(),
            'nb_iov_rouge' => $lignesAct->where('feu', 'rouge')->count(),
        ]);
    }

    private function noeudRbm(int $niveau, string $code, string $intitule): array
    {
        return [
            'niveau' => $niveau, 'code' => $code, 'intitule' => $intitule, 'activite' => false,
            'activite_id' => null, 'premiere_ligne' => true,
            'iov_code' => '', 'iov' => '', 'source' => '', 'cible' => null, 'realisation' => null,
            'feu' => null, 'periode' => null,
            'taux_physique' => null, 'prevu' => null, 'engage' => null, 'paye' => null, 'taux_financier' => null,
        ];
    }

    /** Une ligne par IOV rattaché à l'activité (budget réel via PapPerformanceService). */
    private function lignesActivite(Activite $act, PapPerformanceService $perf): Collection
    {
        $financier = [
            'taux_physique' => (float) $act->taux_realisation_physique,
            'prevu' => (float) $act->montant_total,
            'engage' => $perf->montantEngageReel($act),
            'paye' => $perf->montantPayeReel($act),
            'taux_financier' => $perf->tauxFinancierReel($act),
        ];

        $base = [
            'niveau' => 4,
            'code' => $act->code,
            'intitule' => $act->intitule,
            'activite' => true,
            'activite_id' => $act->id,
        ];

        if ($act->indicateurs->isEmpty()) {
            return collect([array_merge($base, $financier, [
                'premiere_ligne' => true,
                'iov_code' => '—',
                'iov' => '—',
                'source' => '—',
                'cible' => null,
                'realisation' => null,
                'feu' => null,
                'periode' => null,
            ])]);
        }

        return $act->indicateurs->values()->map(function (Indicateur $ind, int $index) use ($base, $financier) {
            $mesure = $ind->mesures->sortByDesc('periode')->first();

            return array_merge($base, $index === 0 ? $financier : [
                'taux_physique' => null,
                'prevu' => null,
                'engage' => null,
                'paye' => null,
                'taux_financier' => null,
            ], [
                'premiere_ligne' => $index === 0,
                'iov_code' => $ind->code,
                'iov' => $ind->libelle,
                'source' => $this->sourceIndicateur($ind),
                'cible' => $ind->valeur_cible,
                'realisation' => $mesure?->valeur_realisee,
                'feu' => $mesure?->feu,
                'periode' => $mesure?->periode,
            ]);
        });
    }

    private function sourceIndicateur(Indicateur $ind): string
    {
        $catalogue = $ind->sourcesVerification->pluck('libelle')->filter()->implode(', ');

        return $catalogue !== '' ? $catalogue : ($ind->source_verification ?: '—');
    }

    /** Données structurées du rapport de performance du PAP d'un département (ou global). */
    public function papPerformance(int $exerciceId, ?int $structureId = null): array
    {
        $actQuery = Activite::query()
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId));
        if ($structureId) {
            $actQuery->where('structure_id', $structureId);
        }
        $perf = app(\App\Services\Pap\PapPerformanceService::class);
        $activites = $actQuery->with('ligneBudgetaire')->get();

        $physMoy = round((float) $activites->avg('taux_realisation_physique'), 1);
        $totalPrevu = (float) $activites->sum('montant_total');
        $totalPaye = (float) $activites->sum(fn (Activite $a) => $perf->montantPayeReel($a));
        $finPondere = $totalPrevu > 0 ? round($totalPaye / $totalPrevu * 100, 1) : 0.0;

        $marches = Engagement::where('exercice_id', $exerciceId)->where('type', 'marche')
            ->whereIn('statut', ['vise', 'soumis'])->get();

        $contraintes = $activites->where('taux_realisation_physique', '<', 50)->values();
        $opportunites = $activites->where('taux_realisation_physique', '>=', 90)->values();

        $indicateursRouges = DB::table('mesures as m')
            ->join('indicateurs as i', 'm.indicateur_id', '=', 'i.id')
            ->where('m.feu', 'rouge')->select('i.libelle', 'm.periode', 'm.valeur_realisee', 'i.valeur_cible')
            ->limit(20)->get();

        return [
            'physique_moyen' => $physMoy,
            'financier_moyen' => $finPondere,
            'montant_paye' => round($totalPaye, 2),
            'montant_prevu' => round($totalPrevu, 2),
            'nb_activites' => $activites->count(),
            'activites' => $activites,
            'marches' => $marches,
            'contraintes' => $contraintes,
            'opportunites' => $opportunites,
            'recommandations' => $indicateursRouges,
        ];
    }

    /** Cockpit décisionnel exécutif : KPI globaux, répartitions, évolution mensuelle, alertes. */
    public function cockpit(int $exerciceId): array
    {
        $global = $this->global($exerciceId);

        $pap = app(\App\Services\Pap\PapPerformanceService::class)->executionPap($exerciceId);

        // Évolution mensuelle des décaissements (paiements validés), agrégée en PHP (portable SQLite/MySQL)
        $paiements = Paiement::where('statut', 'valide')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->get(['montant', 'date_paiement']);
        $evolution = collect(range(1, 12))->map(fn ($m) => [
            'mois' => $m,
            'montant' => (float) $paiements->filter(fn ($p) => $p->date_paiement && (int) $p->date_paiement->format('n') === $m)->sum('montant'),
        ]);

        $alertes = [];
        $depassees = DB::table('lignes_budgetaires')->where('exercice_id', $exerciceId)->whereNull('deleted_at')
            ->whereColumn('montant_engage', '>', 'montant_ouvert')->count();
        if ($depassees > 0) {
            $alertes[] = ['niveau' => 'danger', 'message' => "{$depassees} ligne(s) budgétaire(s) en dépassement de crédits."];
        }
        $retard = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->where('taux_realisation_physique', '<', 30)->count();
        if ($retard > 0) {
            $alertes[] = ['niveau' => 'warning', 'message' => "{$retard} activité(s) en retard (taux physique < 30%)."];
        }
        $rouges = DB::table('mesures')->where('feu', 'rouge')->count();
        if ($rouges > 0) {
            $alertes[] = ['niveau' => 'warning', 'message' => "{$rouges} indicateur(s) au rouge."];
        }
        if (empty($alertes)) {
            $alertes[] = ['niveau' => 'success', 'message' => 'Aucune alerte critique détectée.'];
        }

        return [
            'global' => $global,
            'pap' => [
                'activites' => (int) ($pap['activites'] ?? 0),
                'physique' => (float) ($pap['taux_physique'] ?? 0),
                'financier' => (float) ($pap['taux_financier'] ?? 0),
            ],
            'parAxe' => $this->parAxe($exerciceId),
            'parBailleur' => $this->parBailleur($exerciceId),
            'parCategorie' => $this->parCategorie($exerciceId),
            'parStructure' => $this->parStructure($exerciceId),
            'evolution' => $evolution,
            'alertes' => $alertes,
        ];
    }

    /** Exécution du Plan de Passation des Marchés (PPM). */
    public function marchesPpm(int $exerciceId): Collection
    {
        return \App\Models\Marche::where('exercice_id', $exerciceId)
            ->orderBy('numero')->get()
            ->map(fn ($m) => [
                'numero' => $m->numero,
                'objet' => $m->objet,
                'mode' => \App\Models\Marche::MODES[$m->mode_passation] ?? $m->mode_passation,
                'type' => \App\Models\Marche::TYPES[$m->type_marche] ?? $m->type_marche,
                'estime' => (float) $m->montant_estime,
                'attribue' => (float) $m->montant_attribue,
                'statut' => \App\Models\Marche::STATUTS[$m->statut] ?? $m->statut,
                'taux_attribution' => $m->montant_estime > 0 ? round($m->montant_attribue / $m->montant_estime * 100, 1) : 0.0,
            ]);
    }

    /** Registre des contrats : chaîne Besoin → Engagement → Marché → Contrat → Paiement (montants réels). */
    public function contratsRegistre(int $exerciceId): Collection
    {
        // Payé réel par engagement (paiements validés via la chaîne)
        $payeParEngagement = DB::table('paiements as p')
            ->join('ordonnancements as o', 'p.ordonnancement_id', '=', 'o.id')
            ->join('liquidations as l', 'o.liquidation_id', '=', 'l.id')
            ->where('p.statut', 'valide')->whereNull('p.deleted_at')
            ->groupBy('l.engagement_id')
            ->selectRaw('l.engagement_id, COALESCE(SUM(p.montant),0) paye')
            ->pluck('paye', 'engagement_id');

        return \App\Models\Contrat::with(['marche', 'engagement.expressionBesoin'])
            ->where('exercice_id', $exerciceId)->orderBy('numero')->get()
            ->map(function ($c) use ($payeParEngagement) {
                $eng = $c->engagement;
                $engage = $eng && in_array($eng->statut, ['vise', 'soumis'], true) ? (float) $eng->montant_total : 0.0;
                $paye = $eng ? (float) ($payeParEngagement[$eng->id] ?? 0) : 0.0;

                return [
                    'contrat' => $c->numero,
                    'titulaire' => $c->titulaire,
                    'besoin' => $eng?->expressionBesoin?->numero ?? '—',
                    'engagement' => $eng?->numero ?? '—',
                    'marche' => $c->marche?->numero ?? '—',
                    'montant' => (float) $c->montant,
                    'engage' => $engage,
                    'paye' => $paye,
                    'statut' => \App\Models\Contrat::STATUTS[$c->statut] ?? $c->statut,
                ];
            });
    }

    /** Suivi des recommandations : détail + synthèse par source. */
    public function recommandations(int $exerciceId): array
    {
        $detail = \App\Models\Recommandation::with('structure')
            ->where('exercice_id', $exerciceId)->orderBy('source')->orderBy('numero')->get()
            ->map(fn ($r) => [
                'numero' => $r->numero,
                'source' => \App\Models\Recommandation::SOURCES[$r->source] ?? $r->source,
                'source_cle' => $r->source,
                'libelle' => $r->libelle,
                'structure' => $r->structure?->sigle ?? $r->structure?->libelle ?? '—',
                'priorite' => \App\Models\Recommandation::PRIORITES[$r->priorite] ?? $r->priorite,
                'echeance' => optional($r->echeance)->format('d/m/Y') ?? '—',
                'statut' => \App\Models\Recommandation::STATUTS[$r->statut] ?? $r->statut,
                'taux' => (float) $r->taux_execution,
            ]);

        $parSource = collect(\App\Models\Recommandation::SOURCES)
            ->map(function ($label, $cle) use ($detail) {
                $grp = $detail->where('source_cle', $cle);

                return [
                    'source' => $label,
                    'total' => $grp->count(),
                    'realisees' => $grp->where('statut', 'Réalisée')->count(),
                    'taux_moyen' => $grp->count() ? round($grp->avg('taux'), 1) : 0.0,
                ];
            })->filter(fn ($r) => $r['total'] > 0)->values();

        return ['detail' => $detail, 'parSource' => $parSource];
    }

    /** Ateliers & formations : détail + synthèse par type + répartition genre. */
    public function formations(int $exerciceId): array
    {
        $detail = \App\Models\Formation::with('structure')
            ->where('exercice_id', $exerciceId)->orderBy('numero')->get()
            ->map(fn ($f) => [
                'numero' => $f->numero,
                'intitule' => $f->intitule,
                'type' => \App\Models\Formation::TYPES[$f->type] ?? $f->type,
                'type_cle' => $f->type,
                'participants' => $f->nb_participants,
                'hommes' => $f->nb_hommes,
                'femmes' => $f->nb_femmes,
                'cout' => (float) $f->cout,
                'satisfaction' => $f->niveau_satisfaction !== null ? (float) $f->niveau_satisfaction : null,
                'statut' => \App\Models\Formation::STATUTS[$f->statut] ?? $f->statut,
            ]);

        $parType = collect(\App\Models\Formation::TYPES)
            ->map(function ($label, $cle) use ($detail) {
                $grp = $detail->where('type_cle', $cle);

                return [
                    'type' => $label, 'sessions' => $grp->count(),
                    'participants' => $grp->sum('participants'), 'cout' => $grp->sum('cout'),
                ];
            })->filter(fn ($r) => $r['sessions'] > 0)->values();

        return [
            'detail' => $detail,
            'parType' => $parType,
            'hommes' => $detail->sum('hommes'),
            'femmes' => $detail->sum('femmes'),
            'cout_total' => $detail->sum('cout'),
        ];
    }

    /** Immobilisations : détail + synthèse par catégorie + totaux patrimoniaux. */
    public function immobilisations(int $exerciceId): array
    {
        return app(ImmobilisationService::class)->syntheseExercice($exerciceId);
    }

    /** Nomenclature budgétaire : comptes, synthèse par titre et liste complète à plat. */
    public function nomenclature(): array
    {
        $detail = \App\Models\BudgetRubrique::with('paragraphe.article.titre')
            ->orderBy('code')->get()
            ->map(fn ($r) => [
                'sens' => \App\Models\BudgetTitre::SENS[$r->paragraphe?->article?->titre?->sens] ?? ($r->paragraphe?->article?->titre?->sens ?? '—'),
                'titre' => $r->paragraphe?->article?->titre?->code ?? '—',
                'article' => $r->paragraphe?->article?->code ?? '—',
                'paragraphe' => $r->paragraphe?->code ?? '—',
                'rubrique' => $r->code,
                'intitule' => $r->intitule,
            ]);

        $parTitre = \App\Models\BudgetTitre::withCount(['articles'])
            ->orderBy('sens')->orderBy('code')->get()
            ->map(fn ($t) => [
                'sens' => \App\Models\BudgetTitre::SENS[$t->sens] ?? $t->sens,
                'code' => $t->code,
                'intitule' => $t->intitule,
                'rubriques' => $detail->where('titre', $t->code)->count(),
            ]);

        return [
            'detail' => $detail,
            'parTitre' => $parTitre,
            'comptes' => [
                'titres' => \App\Models\BudgetTitre::count(),
                'articles' => \App\Models\BudgetArticle::count(),
                'paragraphes' => \App\Models\BudgetParagraphe::count(),
                'rubriques' => \App\Models\BudgetRubrique::count(),
                'lignes' => \App\Models\LigneBudgetaire::count(),
            ],
        ];
    }

    /** Exécution par STRUCTURE de rattachement (département), top N. */
    public function parStructure(int $exerciceId, int $limit = 12): Collection
    {
        return DB::table('lignes_budgetaires as lb')
            ->leftJoin('structures as s', 'lb.structure_id', '=', 's.id')
            ->where('lb.exercice_id', $exerciceId)->whereNull('lb.deleted_at')
            ->groupBy('s.id', 's.libelle', 's.sigle')
            ->selectRaw('COALESCE(s.sigle, s.libelle, "(non rattachée)") libelle,
                         COALESCE(SUM(lb.montant_ouvert),0) ouvert, COALESCE(SUM(lb.montant_engage),0) engage,
                         COALESCE(SUM(lb.montant_paye),0) paye')
            ->orderByDesc('ouvert')->limit($limit)->get()
            ->map(fn ($r) => ['libelle' => $r->libelle, 'ouvert' => (float) $r->ouvert, 'engage' => (float) $r->engage, 'paye' => (float) $r->paye]);
    }

    /** Ventile les montants des lignes budgétaires par source (pivot multi-sources ou FK unique). */
    private function ventilerMontantsParSource(int $exerciceId): array
    {
        $lignes = DB::table('lignes_budgetaires')
            ->where('exercice_id', $exerciceId)->whereNull('deleted_at')
            ->get(['id', 'source_financement_id', 'montant_ouvert', 'montant_revise', 'montant_engage', 'montant_liquide', 'montant_ordonnance', 'montant_paye']);

        $pivots = DB::table('ligne_source_financement')
            ->whereIn('ligne_budgetaire_id', $lignes->pluck('id'))
            ->get()
            ->groupBy('ligne_budgetaire_id');

        $bucket = [];

        foreach ($lignes as $lb) {
            $alloue = (float) ($lb->montant_revise ?? $lb->montant_ouvert);

            $montants = [
                'ouvert' => (float) $lb->montant_ouvert,
                'alloue' => $alloue,
                'engage' => (float) $lb->montant_engage,
                'liquide' => (float) $lb->montant_liquide,
                'ordonnance' => (float) $lb->montant_ordonnance,
                'paye' => (float) $lb->montant_paye,
            ];

            if ($pivots->has($lb->id)) {
                $parts = $pivots[$lb->id];
                $base = max((float) $parts->sum('montant'), $alloue, 0.01);
                foreach ($parts as $p) {
                    $ratio = (float) $p->montant / $base;
                    $this->addSourceBucket($bucket, (int) $p->source_financement_id, $montants, $ratio);
                }
            } else {
                $this->addSourceBucket($bucket, (int) ($lb->source_financement_id ?? 0), $montants, 1.0);
            }
        }

        return $bucket;
    }

    /** @param array<int,array<string,float>> $bucket */
    private function addSourceBucket(array &$bucket, int $sourceId, array $montants, float $ratio): void
    {
        if (! isset($bucket[$sourceId])) {
            $bucket[$sourceId] = ['ouvert' => 0, 'alloue' => 0, 'engage' => 0, 'liquide' => 0, 'ordonnance' => 0, 'paye' => 0];
        }

        foreach ($montants as $k => $v) {
            $bucket[$sourceId][$k] += $v * $ratio;
        }
    }

    /** Calcule les taux et renvoie une ligne de synthèse normalisée. */
    private function ligneSynthese(
        float $ouvert,
        float $engage,
        float $liquide,
        float $ordonnance,
        float $paye,
        ?float $alloue = null,
    ): array {
        return SuiviFinancierCalculator::synthese($ouvert, $engage, $liquide, $ordonnance, $paye, $alloue);
    }

    /**
     * Ajoute si nécessaire une ligne « reliquat » pour garantir Σ(détail) = global (réconciliation stricte).
     */
    private function avecReliquat(Collection $detail, array $global, string $libelleReliquat, array $cles): Collection
    {
        $reliquat = $this->ligneSynthese(
            $global['ouvert'] - $detail->sum('ouvert'),
            $global['engage'] - $detail->sum('engage'),
            $global['liquide'] - $detail->sum('liquide'),
            $global['ordonnance'] - $detail->sum('ordonnance'),
            $global['paye'] - $detail->sum('paye'),
            ($global['alloue'] ?? $global['ouvert']) - $detail->sum(fn ($r) => $r['alloue'] ?? $r['ouvert']),
        );

        if (round($reliquat['ouvert'], 2) > 0 || round($reliquat['engage'], 2) > 0) {
            $detail = $detail->push(array_merge($cles, ['intitule' => $libelleReliquat, 'libelle' => $libelleReliquat], $reliquat));
        }

        return $detail->values();
    }

    /** Performance consolidée par pilier (financier + physique). */
    public function parPilier(int $exerciceId): Collection
    {
        $axes = $this->parAxe($exerciceId);

        $physiques = DB::table('activites as a')
            ->join('sous_produits as sp', 'a.sous_produit_id', '=', 'sp.id')
            ->join('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as pil', 'ax.pilier_id', '=', 'pil.id')
            ->where('pil.exercice_id', $exerciceId)
            ->whereNull('a.deleted_at')
            ->groupBy('pil.code', 'pil.intitule')
            ->selectRaw('pil.code, pil.intitule, AVG(COALESCE(a.taux_realisation_physique, 0)) as taux_physique')
            ->get()
            ->keyBy('code');

        return $axes->groupBy('pilier')->map(function (Collection $group, string $pilierCode) use ($physiques) {
            $alloue = $group->sum(fn ($r) => $r['alloue'] ?? $r['ouvert']);
            $synthese = $this->ligneSynthese(
                $group->sum('ouvert'),
                $group->sum('engage'),
                $group->sum('liquide'),
                $group->sum('ordonnance'),
                $group->sum('paye'),
                $alloue,
            );
            $phys = round((float) ($physiques->get($pilierCode)?->taux_physique ?? 0), 1);
            $meta = $physiques->get($pilierCode);

            return array_merge([
                'code' => $pilierCode,
                'libelle' => $meta?->intitule ?? $pilierCode,
                'axes' => $group->count(),
                'taux_physique' => $phys,
                'indice_performance' => round(($phys + $synthese['taux_execution']) / 2, 1),
            ], $synthese);
        })
            ->sortByDesc('indice_performance')
            ->values()
            ->map(fn (array $row, int $i) => array_merge($row, ['rang' => $i + 1]));
    }

    /** Classement des départements (délégation PapPerformanceService). */
    public function performanceDepartements(int $exerciceId): Collection
    {
        return app(PapPerformanceService::class)->classementDepartements($exerciceId);
    }

    /** Synthèse Suivi-Évaluation : scores courants + historique des évaluations (P1). */
    public function suiviEvaluation(int $exerciceId): array
    {
        return app(\App\Services\Suivi\SuiviEvaluationService::class)->rapportDonnees($exerciceId);
    }
}
