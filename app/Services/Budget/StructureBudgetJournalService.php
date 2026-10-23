<?php



namespace App\Services\Budget;



use App\Models\JournalAudit;

use App\Models\LigneBudgetaire;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use Illuminate\Support\Collection;



/** Consultation du journal d'audit du module Structure du Budget. */

class StructureBudgetJournalService

{

    /** @var list<string> */

    private const ACTIONS_MODULE = [

        'structure_budget_ligne_creee',

        'structure_budget_ligne_modifiee',

        'structure_budget_ligne_supprimee',

        'structure_budget_ligne_archivee',

        'structure_budget_ligne_soumise',

        'structure_budget_ligne_validee',

        'structure_budget_ligne_rejetee',

        'structure_budget_export',

        'structure_budget_import',

    ];



    public function journalExercice(int $exerciceId, ?string $action = null, int $perPage = 30): LengthAwarePaginator

    {

        $ligneIds = LigneBudgetaire::withTrashed()

            ->where('exercice_id', $exerciceId)

            ->pluck('id');



        return $this->baseQuery($ligneIds)

            ->when($action, fn ($q) => $q->where('action', $action))

            ->orderByDesc('created_at')

            ->paginate($perPage)

            ->withQueryString();

    }



    public function journalLigne(LigneBudgetaire $ligne, int $perPage = 20): LengthAwarePaginator

    {

        return JournalAudit::query()

            ->where(function ($q) use ($ligne) {

                $q->where(function ($q2) use ($ligne) {

                    $q2->where('auditable_type', LigneBudgetaire::class)

                        ->where('auditable_id', $ligne->id);

                })->orWhere(function ($q2) use ($ligne) {

                    $q2->whereIn('action', self::ACTIONS_MODULE)

                        ->whereJsonContains('apres->code', $ligne->code);

                });

            })

            ->orderByDesc('created_at')

            ->paginate($perPage);

    }



    /** Lignes en attente de validation pour un exercice. */

    public function lignesEnAttente(int $exerciceId): Collection

    {

        return LigneBudgetaire::with(['structure', 'rubrique'])

            ->where('exercice_id', $exerciceId)

            ->where('statut_validation', 'soumis')

            ->whereNull('deleted_at')

            ->orderBy('soumis_le')

            ->get();

    }



    public function actionsDisponibles(): array

    {

        return self::ACTIONS_MODULE;

    }



    private function baseQuery(Collection $ligneIds)

    {

        return JournalAudit::query()->where(function ($q) use ($ligneIds) {

            $q->where(function ($q2) use ($ligneIds) {

                $q2->where('auditable_type', LigneBudgetaire::class)

                    ->whereIn('auditable_id', $ligneIds);

            })->orWhereIn('action', self::ACTIONS_MODULE);

        });

    }

}


