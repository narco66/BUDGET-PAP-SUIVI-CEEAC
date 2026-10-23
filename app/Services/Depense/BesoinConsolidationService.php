<?php

namespace App\Services\Depense;

use App\Models\BesoinConsolidation;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Consolidation institutionnelle des expressions de besoin (phase D1). */
class BesoinConsolidationService
{
    /** @return array<string,mixed> */
    public function synthese(int $exerciceId, ?int $structureId = null): array
    {
        $besoins = $this->besoinsEligibles($exerciceId, $structureId);
        $groupes = $this->grouperParStructure($besoins);
        $doublons = $this->detecterDoublons($besoins);

        return [
            'total_besoins' => $besoins->count(),
            'montant_total' => round((float) $besoins->sum('montant_estime'), 2),
            'par_structure' => $groupes,
            'doublons' => $doublons,
            'doublons_count' => count($doublons),
        ];
    }

    public function consolider(int $exerciceId, ?int $structureId, int $userId): BesoinConsolidation
    {
        return DB::transaction(function () use ($exerciceId, $structureId, $userId) {
            $synthese = $this->synthese($exerciceId, $structureId);
            $exercice = Exercice::findOrFail($exerciceId);
            $version = BesoinConsolidation::where('exercice_id', $exerciceId)
                ->when($structureId, fn ($q) => $q->where('structure_id', $structureId), fn ($q) => $q->whereNull('structure_id'))
                ->count() + 1;

            $ref = $structureId
                ? "CONSOL-BESOIN-{$exercice->annee}-S{$structureId}-v{$version}"
                : "CONSOL-BESOIN-{$exercice->annee}-INST-v{$version}";

            return BesoinConsolidation::create([
                'exercice_id' => $exerciceId,
                'structure_id' => $structureId,
                'reference' => $ref,
                'besoins_count' => $synthese['total_besoins'],
                'doublons_elimines' => $synthese['doublons_count'],
                'montant_total' => $synthese['montant_total'],
                'snapshot' => $synthese,
                'statut' => 'valide',
                'user_id' => $userId,
            ]);
        });
    }

    /** @return Collection<int, ExpressionBesoin> */
    private function besoinsEligibles(int $exerciceId, ?int $structureId): Collection
    {
        return ExpressionBesoin::with('structure', 'activite', 'ligneBudgetaire')
            ->where('exercice_id', $exerciceId)
            ->whereIn('statut', ['soumis', 'valide'])
            ->when($structureId, fn ($q) => $q->where('structure_id', $structureId))
            ->orderBy('structure_id')
            ->orderBy('numero')
            ->get();
    }

    /** @param Collection<int, ExpressionBesoin> $besoins */
    private function grouperParStructure(Collection $besoins): array
    {
        return $besoins->groupBy('structure_id')->map(function (Collection $groupe) {
            $structure = $groupe->first()?->structure;

            return [
                'structure_id' => $structure?->id,
                'structure' => $structure?->sigle ?? $structure?->libelle ?? 'Non rattaché',
                'count' => $groupe->count(),
                'montant' => round((float) $groupe->sum('montant_estime'), 2),
            ];
        })->values()->all();
    }

    /**
     * Doublons : même structure, activité, montant et objet normalisé.
     *
     * @param Collection<int, ExpressionBesoin> $besoins
     * @return list<array<string,mixed>>
     */
    private function detecterDoublons(Collection $besoins): array
    {
        $vu = [];
        $doublons = [];

        foreach ($besoins as $besoin) {
            $cle = implode('|', [
                $besoin->structure_id ?? 0,
                $besoin->activite_id ?? 0,
                number_format((float) $besoin->montant_estime, 2, '.', ''),
                $this->normaliserObjet($besoin->objet),
            ]);

            if (isset($vu[$cle])) {
                $doublons[] = [
                    'original' => $vu[$cle],
                    'doublon' => $besoin->numero,
                    'objet' => $besoin->objet,
                    'montant' => (float) $besoin->montant_estime,
                ];
            } else {
                $vu[$cle] = $besoin->numero;
            }
        }

        return $doublons;
    }

    private function normaliserObjet(string $objet): string
    {
        return Str::ascii(Str::lower(trim($objet)));
    }
}
