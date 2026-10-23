<?php

namespace App\Services\Budget;

use App\Models\LigneBudgetaire;
use App\Models\LigneSourceFinancement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Synchronisation financement multi-sources sur les lignes budgétaires. */
class LigneFinancementService
{
    /**
     * @param  array<int,array{source_financement_id:int,montant:float,pourcentage?:float,est_principale?:bool}>  $sources
     */
    public function synchroniser(LigneBudgetaire $ligne, array $sources): void
    {
        $sources = array_values(array_filter($sources, fn ($s) => (float) ($s['montant'] ?? 0) > 0 && ! empty($s['source_financement_id'])));

        DB::transaction(function () use ($ligne, $sources) {
            $ligne->sourcesFinancement()->delete();

            if ($sources === []) {
                return;
            }

            $alloue = (float) $ligne->montant_alloue;
            $totalMontants = array_sum(array_map(fn ($s) => (float) $s['montant'], $sources));

            if ($alloue > 0 && abs($totalMontants - $alloue) > 0.05) {
                throw new RuntimeException('La somme des montants par source doit être égale au crédit alloué de la ligne.');
            }

            $principaleId = null;
            foreach ($sources as $i => $src) {
                $estPrincipale = (bool) ($src['est_principale'] ?? ($i === 0));
                LigneSourceFinancement::create([
                    'ligne_budgetaire_id' => $ligne->id,
                    'source_financement_id' => (int) $src['source_financement_id'],
                    'montant' => (float) $src['montant'],
                    'pourcentage' => isset($src['pourcentage']) ? (float) $src['pourcentage'] : ($alloue > 0 ? round((float) $src['montant'] / $alloue * 100, 4) : null),
                    'est_principale' => $estPrincipale,
                ]);
                if ($estPrincipale) {
                    $principaleId = (int) $src['source_financement_id'];
                }
            }

            if ($principaleId && $ligne->source_financement_id !== $principaleId) {
                $ligne->update(['source_financement_id' => $principaleId]);
            }
        });
    }

    /** @return array<int,array{source_financement_id:int,montant:float,pourcentage:?float,est_principale:bool}> */
    public function extrairePourFormulaire(LigneBudgetaire $ligne): array
    {
        $pivots = $ligne->sourcesFinancement()->orderByDesc('est_principale')->get();

        if ($pivots->isNotEmpty()) {
            return $pivots->map(fn (LigneSourceFinancement $p) => [
                'source_financement_id' => $p->source_financement_id,
                'montant' => (float) $p->montant,
                'pourcentage' => $p->pourcentage !== null ? (float) $p->pourcentage : null,
                'est_principale' => (bool) $p->est_principale,
            ])->all();
        }

        if ($ligne->source_financement_id) {
            return [[
                'source_financement_id' => $ligne->source_financement_id,
                'montant' => (float) $ligne->montant_alloue,
                'pourcentage' => 100.0,
                'est_principale' => true,
            ]];
        }

        return [];
    }
}
