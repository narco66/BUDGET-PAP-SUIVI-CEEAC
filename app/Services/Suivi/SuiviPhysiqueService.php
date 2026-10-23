<?php

namespace App\Services\Suivi;

use App\Models\Activite;
use App\Models\AlertePerformance;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\RealisationProduit;
use App\Models\RealisationSousProduit;
use App\Services\Pap\PapPerformanceService;
use App\Services\Alertes\AlerteRegistryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Consolidation produits, roll-up tâches, alertes persistées (phase S4). */
class SuiviPhysiqueService
{
    public function __construct(
        private readonly PapPerformanceService $performance,
        private readonly AlerteRegistryService $alertes,
    ) {
    }

    /** Taux physique moyen des tâches actives d'une activité. */
    public function tauxDepuisTaches(Activite $activite): ?float
    {
        $moyenne = $activite->taches()
            ->where(fn ($q) => $q->whereNull('statut_referentiel')->orWhere('statut_referentiel', 'actif'))
            ->avg('taux_realisation');

        return $moyenne !== null ? round((float) $moyenne, 2) : null;
    }

    /** Synchronise le taux physique de l'activité depuis ses tâches (si tâches présentes). */
    public function synchroniserTauxDepuisTaches(Activite $activite): bool
    {
        $taux = $this->tauxDepuisTaches($activite);
        if ($taux === null) {
            return false;
        }

        if ((float) $activite->taux_realisation_physique === $taux) {
            return false;
        }

        $activite->update(['taux_realisation_physique' => $taux]);

        return true;
    }

    /** Recalcule le taux physique depuis quantités si renseignées. */
    public function appliquerQuantites(Collecte $collecte): void
    {
        $prevu = (float) ($collecte->quantite_prevue ?? 0);
        $effectif = (float) ($collecte->quantite_realisee ?? 0);

        if ($prevu > 0 && $collecte->quantite_realisee !== null) {
            $collecte->taux_physique = min(100, round($effectif / $prevu * 100, 2));
        }
    }

    /** Persiste la consolidation par produit lors de la clôture de campagne. */
    public function consoliderProduits(CampagneSuivi $campagne): Collection
    {
        RealisationProduit::where('campagne_id', $campagne->id)->delete();

        $lignes = DB::table('produits as pr')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as p', 'ax.pilier_id', '=', 'p.id')
            ->leftJoin('sous_produits as sp', 'sp.produit_id', '=', 'pr.id')
            ->leftJoin('activites as a', function ($j) {
                $j->on('a.sous_produit_id', '=', 'sp.id')->whereNull('a.deleted_at');
            })
            ->leftJoin('collectes as c', function ($j) use ($campagne) {
                $j->on('c.activite_id', '=', 'a.id')
                    ->where('c.campagne_id', $campagne->id)
                    ->where('c.statut', 'validee');
            })
            ->leftJoin('lignes_budgetaires as lb', 'a.ligne_budgetaire_id', '=', 'lb.id')
            ->where('p.exercice_id', $campagne->exercice_id)
            ->whereNull('pr.deleted_at')
            ->groupBy('pr.id', 'pr.code', 'pr.intitule')
            ->selectRaw('pr.id produit_id, pr.code, pr.intitule,
                COUNT(DISTINCT a.id) nb_activites,
                COUNT(DISTINCT c.id) nb_collectes_validees,
                ROUND(AVG(c.taux_physique), 2) taux_physique,
                ROUND(AVG(CASE
                    WHEN a.ligne_budgetaire_id IS NOT NULL AND a.montant_total > 0
                    THEN (lb.montant_paye / a.montant_total * 100)
                    ELSE c.taux_financier
                END), 2) taux_financier')
            ->get();

        return $lignes->map(function ($r) use ($campagne) {
            return RealisationProduit::create([
                'campagne_id' => $campagne->id,
                'produit_id' => $r->produit_id,
                'taux_physique' => (float) ($r->taux_physique ?? 0),
                'taux_financier' => (float) ($r->taux_financier ?? 0),
                'nb_activites' => (int) $r->nb_activites,
                'nb_collectes_validees' => (int) $r->nb_collectes_validees,
            ]);
        });
    }

    /** Détecte et persiste les alertes de performance pour un exercice. */
    public function detecterAlertes(int $exerciceId, ?int $campagneId = null): int
    {
        $query = AlertePerformance::where('exercice_id', $exerciceId)->where('resolu', false);
        if ($campagneId) {
            $query->where('campagne_id', $campagneId);
        }
        $query->delete();

        $count = 0;
        $campagne = $campagneId ? CampagneSuivi::find($campagneId) : null;

        foreach ($this->performance->activitesEnRetard($exerciceId, 50) as $activite) {
            $ap = AlertePerformance::create([
                'exercice_id' => $exerciceId,
                'campagne_id' => $campagne?->id,
                'activite_id' => $activite->id,
                'produit_id' => $activite->produit_id,
                'type' => 'sous_performance_physique',
                'gravite' => (float) ($activite->taux_realisation_physique ?? 0) < 25 ? 'elevee' : 'moyenne',
                'reference' => $activite->code,
                'message' => "Activité {$activite->code} : taux physique ".($activite->taux_realisation_physique ?? 0).'% (< 50 %).',
                'detecte_le' => now(),
            ]);
            $this->alertes->synchroniserDepuisPerformance($ap);
            $count++;
        }

        Collecte::query()
            ->when($campagneId, fn ($q) => $q->where('campagne_id', $campagneId))
            ->whereHas('campagne', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->where('statut', 'validee')
            ->where(fn ($q) => $q->whereNull('cause_ecart')->orWhere('cause_ecart', ''))
            ->whereRaw('ABS(ecart) >= 15')
            ->with('activite')
            ->each(function (Collecte $c) use ($exerciceId, $campagne, &$count) {
                $ap = AlertePerformance::create([
                    'exercice_id' => $exerciceId,
                    'campagne_id' => $c->campagne_id,
                    'activite_id' => $c->activite_id,
                    'produit_id' => $c->activite?->produit_id,
                    'type' => 'ecart_phys_fin_sans_cause',
                    'gravite' => abs((float) $c->ecart) >= 30 ? 'elevee' : 'moyenne',
                    'reference' => $c->activite?->code,
                    'message' => "Écart phys/fin {$c->ecart} pts sans cause documentée (activité {$c->activite?->code}).",
                    'detecte_le' => now(),
                ]);
                $this->alertes->synchroniserDepuisPerformance($ap);
                $count++;
            });

        return $count;
    }

    /** Persiste la consolidation par sous-produit lors de la clôture (phase S3). */
    public function consoliderSousProduits(CampagneSuivi $campagne): Collection
    {
        RealisationSousProduit::where('campagne_id', $campagne->id)->delete();

        $lignes = DB::table('sous_produits as sp')
            ->join('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as p', 'ax.pilier_id', '=', 'p.id')
            ->leftJoin('activites as a', function ($j) {
                $j->on('a.sous_produit_id', '=', 'sp.id')->whereNull('a.deleted_at');
            })
            ->leftJoin('collectes as c', function ($j) use ($campagne) {
                $j->on('c.activite_id', '=', 'a.id')
                    ->where('c.campagne_id', $campagne->id)
                    ->where('c.statut', 'validee');
            })
            ->leftJoin('lignes_budgetaires as lb', 'a.ligne_budgetaire_id', '=', 'lb.id')
            ->where('p.exercice_id', $campagne->exercice_id)
            ->whereNull('sp.deleted_at')
            ->groupBy('sp.id', 'sp.code', 'sp.intitule')
            ->selectRaw('sp.id sous_produit_id, sp.code, sp.intitule,
                COUNT(DISTINCT a.id) nb_activites,
                COUNT(DISTINCT c.id) nb_collectes_validees,
                ROUND(AVG(c.taux_physique), 2) taux_physique,
                ROUND(AVG(CASE
                    WHEN a.ligne_budgetaire_id IS NOT NULL AND a.montant_total > 0
                    THEN (lb.montant_paye / a.montant_total * 100)
                    ELSE c.taux_financier
                END), 2) taux_financier')
            ->get();

        return $lignes->map(fn ($r) => RealisationSousProduit::create([
            'campagne_id' => $campagne->id,
            'sous_produit_id' => $r->sous_produit_id,
            'taux_physique' => (float) ($r->taux_physique ?? 0),
            'taux_financier' => (float) ($r->taux_financier ?? 0),
            'nb_activites' => (int) $r->nb_activites,
            'nb_collectes_validees' => (int) $r->nb_collectes_validees,
        ]));
    }

    /** Propage le taux des tâches vers la collecte si aucun taux/quantité saisi (phase S3). */
    public function enrichirDepuisTaches(Collecte $collecte): void
    {
        if (! config('suivi.sync_taches_auto', true) || ! $collecte->activite) {
            return;
        }

        $tauxTaches = $this->tauxDepuisTaches($collecte->activite);
        if ($tauxTaches === null) {
            return;
        }

        if ((float) ($collecte->taux_physique ?? 0) <= 0 && ! $collecte->quantite_prevue) {
            $collecte->taux_physique = $tauxTaches;
        }
    }

    /** Clôture campagne : consolidation produits + sous-produits + alertes. */
    public function cloturerCampagne(CampagneSuivi $campagne): array
    {
        $produits = $this->consoliderProduits($campagne);
        $sousProduits = $this->consoliderSousProduits($campagne);
        $alertes = $this->detecterAlertes($campagne->exercice_id, $campagne->id);
        $campagne->update(['statut' => 'cloturee']);

        return [
            'produits_consolides' => $produits->count(),
            'sous_produits_consolides' => $sousProduits->count(),
            'alertes_creees' => $alertes,
        ];
    }
}
