<?php

namespace App\Services\Interop;

use App\Models\EcritureComptable;
use App\Models\Encaissement;
use App\Models\Paiement;
use App\Services\Depense\DevisePaiementService;
use App\Services\Depense\EcritureComptableService;
use Illuminate\Support\Collection;

/** Connecteur comptable TOMPRO — export dépenses / recettes SYSCOHADA (P2-8). */
class TomproService
{
    public function __construct(
        private readonly EcritureComptableService $ecritures,
        private readonly DevisePaiementService $devises,
    ) {}

    /** @param  array{depuis?: string, limit?: int, exercice_id?: int, non_exportes?: bool}  $filtres */
    public function exportPaiements(array $filtres): Collection
    {
        $limit = min(
            (int) ($filtres['limit'] ?? config('interoperabilite.tompro.default_limit', 200)),
            (int) config('interoperabilite.tompro.max_limit', 500),
        );

        return Paiement::with([
            'ordonnancement.liquidation.engagement.imputations.ligneBudgetaire.rubrique',
            'ecrituresComptables',
            'details.ligneBudgetaire',
        ])
            ->where('statut', 'valide')
            ->when(! empty($filtres['depuis']), fn ($q) => $q->whereDate('valide_le', '>=', $filtres['depuis']))
            ->when(! empty($filtres['exercice_id']), fn ($q) => $q->whereHas(
                'ordonnancement.liquidation.engagement',
                fn ($e) => $e->where('exercice_id', $filtres['exercice_id']),
            ))
            ->when(! empty($filtres['non_exportes']), fn ($q) => $q->where(function ($sub) {
                $sub->whereHas('ecrituresComptables', fn ($e) => $e->where('statut', 'valide'))
                    ->orWhereDoesntHave('ecrituresComptables');
            }))
            ->latest('valide_le')
            ->limit($limit)
            ->get()
            ->map(fn (Paiement $p) => $this->formaterPaiement($p));
    }

    /** @param  array{depuis?: string, limit?: int, exercice_id?: int, non_exportes?: bool}  $filtres */
    public function exportEncaissements(array $filtres): Collection
    {
        $limit = min(
            (int) ($filtres['limit'] ?? config('interoperabilite.tompro.default_limit', 200)),
            (int) config('interoperabilite.tompro.max_limit', 500),
        );

        return Encaissement::with(['recette.rubrique', 'recette.exercice', 'ecrituresComptables'])
            ->whereIn('statut', Encaissement::STATUTS_VERROUILLES)
            ->when(! empty($filtres['depuis']), fn ($q) => $q->whereDate('valide_le', '>=', $filtres['depuis']))
            ->when(! empty($filtres['exercice_id']), fn ($q) => $q->whereHas(
                'recette',
                fn ($r) => $r->where('exercice_id', $filtres['exercice_id']),
            ))
            ->when(! empty($filtres['non_exportes']), fn ($q) => $q->where(function ($sub) {
                $sub->whereHas('ecrituresComptables', fn ($e) => $e->where('statut', 'valide'))
                    ->orWhereDoesntHave('ecrituresComptables');
            }))
            ->latest('valide_le')
            ->limit($limit)
            ->get()
            ->map(fn (Encaissement $e) => $this->formaterEncaissement($e));
    }

    /** @return array<string, mixed> */
    public function referentiel(): array
    {
        return [
            'version' => config('interoperabilite.tompro.version', '1.1'),
            'plan_comptable' => EcritureComptable::COMPTES,
            'journaux' => ['OD' => 'Opérations diverses', 'BQ' => 'Banque'],
            'endpoints' => [
                ['methode' => 'GET', 'chemin' => '/api/v1/tompro/paiements', 'description' => 'Décaissements validés + écritures charges'],
                ['methode' => 'GET', 'chemin' => '/api/v1/tompro/encaissements', 'description' => 'Encaissements comptabilisés + écritures produits'],
                ['methode' => 'GET', 'chemin' => '/api/v1/tompro/synthese', 'description' => 'Volumes en attente d\'export par exercice'],
                ['methode' => 'GET', 'chemin' => '/api/v1/tompro/referentiel', 'description' => 'Plan comptable et catalogue des endpoints'],
            ],
            'filtres_communs' => ['depuis', 'limit', 'exercice_id', 'non_exportes', 'marquer_export'],
        ];
    }

    /** @return array<string, mixed> */
    public function synthese(?int $exerciceId = null): array
    {
        $paiementsQuery = Paiement::query()->where('statut', 'valide');
        $encQuery = Encaissement::query()->whereIn('statut', Encaissement::STATUTS_VERROUILLES);

        if ($exerciceId) {
            $paiementsQuery->whereHas(
                'ordonnancement.liquidation.engagement',
                fn ($e) => $e->where('exercice_id', $exerciceId),
            );
            $encQuery->whereHas('recette', fn ($r) => $r->where('exercice_id', $exerciceId));
        }

        $ecrituresQuery = EcritureComptable::query()->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId));

        return [
            'exercice_id' => $exerciceId,
            'paiements_valides' => (clone $paiementsQuery)->count(),
            'encaissements_comptabilises' => (clone $encQuery)->count(),
            'ecritures_a_exporter' => (clone $ecrituresQuery)->where('statut', 'valide')->count(),
            'ecritures_exportees' => (clone $ecrituresQuery)->where('statut', 'exportee')->count(),
        ];
    }

    public function marquerExport(int $exerciceId): int
    {
        return $this->ecritures->marquerExportees($exerciceId);
    }

    /** @return array<string, mixed> */
    private function formaterPaiement(Paiement $p): array
    {
        $engagement = $p->engagementRacine();
        $lignesEcritures = $p->ecrituresComptables;

        if ($lignesEcritures->isEmpty()) {
            $lignesEcritures = $this->ecritures->genererPourPaiement($p);
        }

        return [
            'type' => 'decaissement',
            'reference' => $p->numero,
            'date' => $p->date_paiement?->toDateString(),
            'montant' => (float) $p->montant,
            'devise' => $p->devise ?? 'XAF',
            'taux_change' => (float) ($p->taux_change ?? 1),
            'montant_xaf' => $this->devises->montantXaf($p),
            'mode_paiement' => $p->mode_paiement,
            'reference_bancaire' => $p->reference_bancaire,
            'engagement' => $engagement->numero,
            'beneficiaire' => $engagement->beneficiaire,
            'imputations' => $engagement->imputations->map(fn ($i) => [
                'compte' => $i->ligneBudgetaire?->rubrique?->code,
                'ligne' => $i->ligneBudgetaire?->code,
                'montant' => (float) $i->montant,
            ])->values(),
            'ecritures_syscohada' => $this->mapperEcritures($lignesEcritures),
        ];
    }

    /** @return array<string, mixed> */
    private function formaterEncaissement(Encaissement $e): array
    {
        $lignesEcritures = $e->ecrituresComptables;

        if ($lignesEcritures->isEmpty()) {
            $lignesEcritures = $this->ecritures->genererPourEncaissement($e);
        }

        $montantXaf = (float) $e->montant_xaf > 0 ? (float) $e->montant_xaf : (float) $e->montant;

        return [
            'type' => 'encaissement',
            'reference' => $e->reference,
            'date' => $e->date_encaissement?->toDateString(),
            'montant' => (float) $e->montant,
            'devise' => $e->devise ?? 'XAF',
            'taux_change' => (float) ($e->taux_change ?? 1),
            'montant_xaf' => $montantXaf,
            'mode_paiement' => $e->mode_paiement,
            'reference_bancaire' => $e->reference_bancaire,
            'recette' => $e->recette?->reference,
            'rubrique' => $e->recette?->rubrique?->code,
            'ecritures_syscohada' => $this->mapperEcritures($lignesEcritures),
        ];
    }

    /** @param  Collection<int, EcritureComptable>  $lignes */
    private function mapperEcritures(Collection $lignes): Collection
    {
        return $lignes->map(fn (EcritureComptable $ec) => [
            'numero_piece' => $ec->numero_piece,
            'journal' => $ec->journal,
            'date' => $ec->date_ecriture?->toDateString(),
            'debit' => $ec->compte_debit,
            'credit' => $ec->compte_credit,
            'montant_xaf' => (float) $ec->montant,
            'libelle' => $ec->libelle,
            'statut_export' => $ec->statut,
        ])->values();
    }
}
