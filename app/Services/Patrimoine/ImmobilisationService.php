<?php

namespace App\Services\Patrimoine;

use App\Models\Immobilisation;
use Illuminate\Support\Collection;

/** Agrégations inventaire / immobilisations (P2-1). */
class ImmobilisationService
{
    /** @return array{detail: Collection, parCategorie: Collection, total_acquisition: float, total_amortissement: float, total_valeur_nette: float} */
    public function syntheseExercice(int $exerciceId): array
    {
        $detail = Immobilisation::with('structure')
            ->where('exercice_id', $exerciceId)
            ->orderBy('numero')
            ->get()
            ->map(fn (Immobilisation $i) => [
                'numero' => $i->numero,
                'designation' => $i->designation,
                'categorie' => Immobilisation::CATEGORIES[$i->categorie] ?? $i->categorie,
                'categorie_cle' => $i->categorie,
                'structure' => $i->structure?->sigle ?? $i->structure?->libelle ?? '—',
                'date_acquisition' => $i->date_acquisition?->format('Y-m-d'),
                'valeur_acquisition' => (float) $i->valeur_acquisition,
                'amortissement_cumule' => $i->amortissementCumule(),
                'valeur_nette' => $i->valeurNette(),
                'statut' => Immobilisation::STATUTS[$i->statut] ?? $i->statut,
                'localisation' => $i->localisation ?? '—',
            ]);

        $parCategorie = collect(Immobilisation::CATEGORIES)
            ->map(function (string $label, string $cle) use ($detail) {
                $grp = $detail->where('categorie_cle', $cle);

                return [
                    'categorie' => $label,
                    'nombre' => $grp->count(),
                    'acquisition' => $grp->sum('valeur_acquisition'),
                    'amortissement' => $grp->sum('amortissement_cumule'),
                    'valeur_nette' => $grp->sum('valeur_nette'),
                ];
            })
            ->filter(fn (array $r) => $r['nombre'] > 0)
            ->values();

        return [
            'detail' => $detail,
            'parCategorie' => $parCategorie,
            'total_acquisition' => $detail->sum('valeur_acquisition'),
            'total_amortissement' => $detail->sum('amortissement_cumule'),
            'total_valeur_nette' => $detail->sum('valeur_nette'),
        ];
    }
}
