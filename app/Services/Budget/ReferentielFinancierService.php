<?php

namespace App\Services\Budget;

use App\Models\ConventionFinancement;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\PartenaireFinancier;
use App\Models\SourceFinancement;
use Illuminate\Support\Facades\DB;

/** Indicateurs et données agrégées du référentiel financier (hub Phase D). */
class ReferentielFinancierService
{
    public function __construct(private readonly BudgetAuditService $audit)
    {
    }

    public function exerciceCourant(?int $exerciceId = null): ?Exercice
    {
        return $exerciceId
            ? Exercice::find($exerciceId)
            : ExerciceService::courant();
    }

    /** @return array<string,mixed> */
    public function indicateurs(?int $exerciceId = null): array
    {
        $exercice = $this->exerciceCourant($exerciceId);
        $exId = $exercice?->id;

        $lignesQuery = LigneBudgetaire::query()->when($exId, fn ($q) => $q->where('exercice_id', $exId));

        return [
            'exercice' => $exercice,
            'comptes' => [
                'exercices' => Exercice::count(),
                'sources_actives' => SourceFinancement::where('actif', true)->count(),
                'partenaires_actifs' => PartenaireFinancier::where('actif', true)->count(),
                'conventions_actives' => ConventionFinancement::where('actif', true)->count(),
                'titres' => (int) DB::table('budget_titres')->whereNull('deleted_at')->count(),
                'rubriques' => (int) DB::table('budget_rubriques')->whereNull('deleted_at')->count(),
                'lignes_budget' => (clone $lignesQuery)->count(),
            ],
            'financement' => [
                'interne' => SourceFinancement::where('type', 'interne_ceeac')->where('actif', true)->count(),
                'externe' => SourceFinancement::where('type', 'externe_ptf')->where('actif', true)->count(),
                'multi_sources' => (int) DB::table('ligne_source_financement')
                    ->when($exId, fn ($q) => $q->whereIn('ligne_budgetaire_id', (clone $lignesQuery)->select('id')))
                    ->distinct('ligne_budgetaire_id')
                    ->count('ligne_budgetaire_id'),
            ],
            'audit' => $this->audit->executer($exId),
        ];
    }

    /** @return \Illuminate\Support\Collection<int,\App\Models\SourceFinancement> */
    public function sourcesPourExport()
    {
        return SourceFinancement::with('partenaire')->orderBy('type')->orderBy('libelle')->get();
    }

    /** @return \Illuminate\Support\Collection<int,\App\Models\PartenaireFinancier> */
    public function partenairesPourExport()
    {
        return PartenaireFinancier::withCount(['sources', 'conventions'])->orderBy('libelle')->get();
    }

    /** @return \Illuminate\Support\Collection<int,\App\Models\ConventionFinancement> */
    public function conventionsPourExport(?int $exerciceId = null)
    {
        return ConventionFinancement::with(['partenaire', 'sourceFinancement', 'exercice'])
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->orderBy('code')
            ->get();
    }
}
