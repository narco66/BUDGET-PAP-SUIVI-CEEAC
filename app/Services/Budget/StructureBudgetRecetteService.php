<?php

namespace App\Services\Budget;

use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/** Contrôles automatiques de recette MOA — exercice 2026 (Lot 7). */
class StructureBudgetRecetteService
{
    /** Total des crédits CEEAC-EM reprise Malabo (Budget2026Seeder). */
    public const TOTAL_OUVERT_2026 = 26_275_514_803;

    /** Nombre de lignes insérées par Budget2026Seeder. */
    public const NB_LIGNES_2026 = 35;

    /** Préfixe des codes Malabo dans Budget2026Seeder. */
    private const PREFIXE_MALABO = 'LB-2026-%';

    /** @return array{ok:bool,score:int,total:int,controles:list<array{code:string,libelle:string,ok:bool,detail:string}>,exercice_id?:int,annee?:int} */
    public function executer(?int $exerciceId = null): array
    {
        $exercice = $exerciceId
            ? Exercice::find($exerciceId)
            : Exercice::firstWhere('annee', 2026);

        if (! $exercice) {
            return [
                'ok' => false,
                'score' => 0,
                'total' => 1,
                'controles' => [[
                    'code' => 'EXERCICE_2026',
                    'libelle' => 'Exercice budgétaire 2026',
                    'ok' => false,
                    'detail' => 'Exercice 2026 introuvable — lancer ExerciceSeeder.',
                ]],
            ];
        }

        $controles = [
            $this->controleTotalOuvert($exercice->id),
            $this->controleNombreLignes($exercice->id),
            $this->controleLignesRattachees($exercice->id),
            $this->controleRollupArbre($exercice->id),
            $this->controleLiaisonsRbm($exercice->id),
            $this->controlePermissions(),
            $this->controleRoutes(),
        ];

        $score = count(array_filter($controles, fn ($c) => $c['ok']));

        return [
            'ok' => $score === count($controles),
            'score' => $score,
            'total' => count($controles),
            'controles' => $controles,
            'exercice_id' => $exercice->id,
            'annee' => $exercice->annee,
        ];
    }

    private function queryMalabo(int $exerciceId)
    {
        return LigneBudgetaire::where('exercice_id', $exerciceId)
            ->whereNull('deleted_at')
            ->where('code', 'like', self::PREFIXE_MALABO);
    }

    private function controleTotalOuvert(int $exerciceId): array
    {
        $query = $this->queryMalabo($exerciceId);
        $ouvert = (float) $query->sum('montant_ouvert');
        $ok = abs($ouvert - self::TOTAL_OUVERT_2026) < 0.01;

        return [
            'code' => 'TOTAL_OUVERT_2026',
            'libelle' => 'Total crédits Malabo (lignes LB-2026-*)',
            'ok' => $ok,
            'detail' => sprintf(
                'Attendu %s XAF — constaté %s XAF (%d lignes).',
                number_format(self::TOTAL_OUVERT_2026, 0, ',', ' '),
                number_format($ouvert, 0, ',', ' '),
                (int) $query->count()
            ),
        ];
    }

    private function controleNombreLignes(int $exerciceId): array
    {
        $nb = (int) $this->queryMalabo($exerciceId)->count();
        $ok = $nb >= self::NB_LIGNES_2026;

        return [
            'code' => 'NB_LIGNES_2026',
            'libelle' => 'Jeu Malabo LB-2026-* chargé',
            'ok' => $ok,
            'detail' => "{$nb} ligne(s) LB-2026-* — minimum attendu ".self::NB_LIGNES_2026.'.',
        ];
    }

    private function controleLignesRattachees(int $exerciceId): array
    {
        $sansStructure = (int) LigneBudgetaire::where('exercice_id', $exerciceId)
            ->whereNull('deleted_at')->whereNull('structure_id')->count();
        $sansRubrique = (int) LigneBudgetaire::where('exercice_id', $exerciceId)
            ->whereNull('deleted_at')->whereNull('rubrique_id')->count();
        $ok = $sansStructure === 0 && $sansRubrique === 0;

        return [
            'code' => 'RATTACHEMENT_REF',
            'libelle' => 'Toutes les lignes ont structure et rubrique',
            'ok' => $ok,
            'detail' => "Sans structure : {$sansStructure} · sans rubrique : {$sansRubrique}.",
        ];
    }

    private function controleRollupArbre(int $exerciceId): array
    {
        $service = app(StructureBudgetService::class);
        $synthese = $service->synthese($exerciceId);
        $racines = $service->arbre($exerciceId);
        $rollup = array_sum(array_column($racines, 'total_ouvert'));
        $ok = abs($rollup - (float) $synthese['ouvert']) < 0.01;

        return [
            'code' => 'ROLLUP_ARBRE',
            'libelle' => 'Consolidation arbre = synthèse globale',
            'ok' => $ok,
            'detail' => sprintf(
                'Rollup racines %s XAF — synthèse %s XAF.',
                number_format($rollup, 0, ',', ' '),
                number_format((float) $synthese['ouvert'], 0, ',', ' ')
            ),
        ];
    }

    private function controleLiaisonsRbm(int $exerciceId): array
    {
        $desync = app(StructureBudgetCoherenceService::class)->compterLiaisonsDesynchronisees($exerciceId);

        return [
            'code' => 'LIAISON_RBM',
            'libelle' => 'Liaisons activité ↔ ligne synchronisées',
            'ok' => $desync === 0,
            'detail' => $desync === 0 ? 'Aucune anomalie.' : "{$desync} liaison(s) désynchronisée(s).",
        ];
    }

    private function controlePermissions(): array
    {
        $requises = ['voir', 'creer', 'modifier', 'valider', 'rejeter', 'exporter', 'importer', 'consulter_journal'];
        $manquantes = array_filter($requises, fn ($a) => ! Permission::where('name', "structure_budget.{$a}")->exists());

        return [
            'code' => 'PERMISSIONS',
            'libelle' => 'Catalogue permissions structure_budget',
            'ok' => $manquantes === [],
            'detail' => $manquantes === [] ? '8 permissions clés présentes.' : 'Manquantes : '.implode(', ', $manquantes).'.',
        ];
    }

    private function controleRoutes(): array
    {
        $noms = [
            'structure-budget.arbre',
            'structure-budget.tableau',
            'structure-budget.wizard',
            'structure-budget.journal',
            'structure-budget.import-export',
            'api.structure-budget.synthese',
        ];
        $absentes = array_filter($noms, fn ($n) => ! Route::has($n));

        return [
            'code' => 'ROUTES',
            'libelle' => 'Routes UI et API enregistrées',
            'ok' => $absentes === [],
            'detail' => $absentes === [] ? '6 routes clés OK.' : 'Absentes : '.implode(', ', $absentes).'.',
        ];
    }
}
