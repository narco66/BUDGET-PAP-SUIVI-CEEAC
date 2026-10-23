<?php

namespace App\Services\Reporting;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Pilier;
use App\Models\SourceFinancement;
use App\Models\Structure;
use App\Models\Tache;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Détection en temps réel des activités du PAP en retard.
 *
 * Critères (cumulés) :
 *  - date de fin prévue dépassée (= MAX(taches.date_fin) < aujourd'hui) ;
 *  - taux d'exécution physique < 100 % ;
 *  - statut non terminé / non clôturé.
 *
 * Performance : un seul sous-select agrégé pour les échéances, eager loading des
 * relations (pas de N+1), `withMax`/`withMin` pour les bornes, pagination en sortie.
 */
class ActivitesRetardService
{
    /** Statuts considérés comme clôturés (exclus de la détection). */
    public const STATUTS_CLOS = ['termine', 'terminee', 'cloture', 'cloturee', 'clos', 'annule', 'annulee'];

    /** Construit la requête des activités en retard pour un exercice, avec filtres. */
    public function builder(int $exerciceId, array $f = []): Builder
    {
        $aujourdHui = Carbon::today()->toDateString();

        // Sous-requête : activités dont la dernière échéance planifiée est dépassée.
        $idsEchus = Tache::query()
            ->select('activite_id')
            ->whereNotNull('date_fin')
            ->groupBy('activite_id')
            ->havingRaw('MAX(date_fin) < ?', [$aujourdHui]);

        $q = Activite::query()
            ->with([
                'produit.axe.pilier',
                'sousProduit',
                'structure',
                'responsable',
                'ligneBudgetaire',
            ])
            ->withMax('taches as date_fin_prevue', 'date_fin')
            ->withMin('taches as date_debut_prevue', 'date_debut')
            ->whereIn('id', $idsEchus)
            ->where('taux_realisation_physique', '<', 100)
            ->whereNotIn('statut', self::STATUTS_CLOS)
            ->whereHas('produit.axe.pilier', fn (Builder $p) => $p->where('exercice_id', $exerciceId));

        $this->appliquerFiltres($q, $f);

        return $q->orderByDesc('date_fin_prevue');
    }

    public function paginer(int $exerciceId, array $f = [], int $parPage = 20): LengthAwarePaginator
    {
        return $this->builder($exerciceId, $f)->paginate($parPage)->withQueryString();
    }

    /** Collection complète (exports) — sans pagination. */
    public function collection(int $exerciceId, array $f = []): Collection
    {
        return $this->builder($exerciceId, $f)->get();
    }

    /** @return array{total:int, montant:float, taux_moyen:float} */
    public function synthese(int $exerciceId, array $f = []): array
    {
        $base = $this->builder($exerciceId, $f)->reorder();

        return [
            'total' => (clone $base)->count(),
            'montant' => (float) (clone $base)->sum('montant_total'),
            'taux_moyen' => round((float) (clone $base)->avg('taux_realisation_physique'), 1),
        ];
    }

    /** Listes d'options pour les filtres (limitées à l'exercice). */
    public function optionsFiltres(int $exerciceId): array
    {
        return [
            'structures' => Structure::orderBy('libelle')->get(['id', 'libelle', 'type']),
            'responsables' => User::orderBy('name')->get(['id', 'name']),
            'piliers' => Pilier::where('exercice_id', $exerciceId)->orderBy('ordre')->get(['id', 'code', 'intitule']),
            'axes' => Axe::whereHas('pilier', fn ($p) => $p->where('exercice_id', $exerciceId))->orderBy('ordre')->get(['id', 'pilier_id', 'code', 'intitule']),
            'sources' => SourceFinancement::where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
        ];
    }

    /** Nombre de jours de retard d'une activité (≥ 0). */
    public function joursRetard(Activite $a): int
    {
        if (! $a->date_fin_prevue) {
            return 0;
        }

        return max(0, Carbon::parse($a->date_fin_prevue)->diffInDays(Carbon::today(), false));
    }

    /** Niveau de criticité du retard : warning (1-15) / orange (16-30) / danger (>30). */
    public function niveau(int $jours): string
    {
        return match (true) {
            $jours > 30 => 'danger',
            $jours > 15 => 'orange',
            default => 'warning',
        };
    }

    /** Budget consommé estimé d'une activité (payé de la ligne, sinon taux financier × budget). */
    public function budgetConsomme(Activite $a): float
    {
        if ($a->ligneBudgetaire) {
            return (float) $a->ligneBudgetaire->montant_paye;
        }

        return round((float) $a->montant_total * (float) $a->taux_realisation_financier / 100, 2);
    }

    private function appliquerFiltres(Builder $q, array $f): void
    {
        if (! empty($f['structure_id'])) {
            $q->where('structure_id', $f['structure_id']);
        }
        if (! empty($f['responsable_id'])) {
            $q->where('responsable_user_id', $f['responsable_id']);
        }
        if (! empty($f['pilier_id'])) {
            $q->whereHas('produit.axe.pilier', fn (Builder $p) => $p->where('piliers.id', $f['pilier_id']));
        }
        if (! empty($f['axe_id'])) {
            $q->whereHas('produit.axe', fn (Builder $a) => $a->where('axes.id', $f['axe_id']));
        }
        if (! empty($f['source_id'])) {
            $q->whereHas('ligneBudgetaire', fn (Builder $l) => $l->where('source_financement_id', $f['source_id']));
        }
        if (! empty($f['du'])) {
            $q->whereHas('taches', fn (Builder $t) => $t->whereDate('date_fin', '>=', $f['du']));
        }
        if (! empty($f['au'])) {
            $q->whereHas('taches', fn (Builder $t) => $t->whereDate('date_fin', '<=', $f['au']));
        }
    }
}
