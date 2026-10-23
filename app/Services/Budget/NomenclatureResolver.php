<?php

namespace App\Services\Budget;

use App\Models\BudgetRubrique;
use Illuminate\Support\Collection;

/** Résolution non ambiguë des rubriques budgétaires (Phase C). */
class NomenclatureResolver
{
    /**
     * Résout une rubrique par code, avec contexte hiérarchique optionnel.
     *
     * @param  array{rubrique_code?:string,paragraphe_code?:string,article_code?:string,titre_code?:string}  $codes
     */
    public function resoudreRubrique(array $codes): ?BudgetRubrique
    {
        $rubriqueCode = trim((string) ($codes['rubrique_code'] ?? ''));
        if ($rubriqueCode === '') {
            return null;
        }

        $query = BudgetRubrique::query()->where('code', $rubriqueCode);

        $paragrapheCode = trim((string) ($codes['paragraphe_code'] ?? ''));
        $articleCode = trim((string) ($codes['article_code'] ?? ''));
        $titreCode = trim((string) ($codes['titre_code'] ?? ''));

        if ($paragrapheCode !== '') {
            $query->whereHas('paragraphe', function ($q) use ($paragrapheCode, $articleCode, $titreCode) {
                $q->where('code', $paragrapheCode);
                if ($articleCode !== '') {
                    $q->whereHas('article', function ($a) use ($articleCode, $titreCode) {
                        $a->where('code', $articleCode);
                        if ($titreCode !== '') {
                            $a->whereHas('titre', fn ($t) => $t->where('code', $titreCode));
                        }
                    });
                }
            });
        }

        $matches = $query->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            return null;
        }

        // Rétrocompatibilité : code rubrique seul si unique dans toute la nomenclature
        $unicite = BudgetRubrique::where('code', $rubriqueCode)->count();

        return $unicite === 1 ? BudgetRubrique::firstWhere('code', $rubriqueCode) : null;
    }

    /** Message d'erreur contextualisé pour l'import. */
    public function messageErreurResolution(array $codes): string
    {
        $rub = $codes['rubrique_code'] ?? '?';
        $parts = array_filter([
            isset($codes['titre_code']) ? "titre {$codes['titre_code']}" : null,
            isset($codes['article_code']) ? "article {$codes['article_code']}" : null,
            isset($codes['paragraphe_code']) ? "paragraphe {$codes['paragraphe_code']}" : null,
            "rubrique {$rub}",
        ]);

        return 'rubrique « '.implode(' › ', $parts).' » introuvable ou ambiguë.';
    }

    /** @return Collection<int,BudgetRubrique> */
    public function rubriquesAmbigues(): Collection
    {
        return BudgetRubrique::query()
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code')
            ->flatMap(fn ($code) => BudgetRubrique::where('code', $code)->with('paragraphe.article.titre')->get());
    }
}
