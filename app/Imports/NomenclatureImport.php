<?php

namespace App\Imports;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import de la nomenclature budgétaire (format à plat : une rubrique par ligne,
 * chaque ligne portant les codes/intitulés de ses parents).
 * En-têtes attendus : sens, titre_code, titre_intitule, article_code, article_intitule,
 * paragraphe_code, paragraphe_intitule, rubrique_code, rubrique_intitule.
 *
 * Produit un rapport d'import détaillé (créés / mis à jour / rejetés avec motif), exploité par
 * l'assistant d'import (prévisualisation + rapport).
 */
class NomenclatureImport implements ToCollection, WithHeadingRow
{
    use Importable;

    /** En-têtes attendus dans le fichier. */
    public const COLONNES = [
        'sens', 'titre_code', 'titre_intitule', 'article_code', 'article_intitule',
        'paragraphe_code', 'paragraphe_intitule', 'rubrique_code', 'rubrique_intitule',
    ];

    public int $lignesImportees = 0;
    public int $crees = 0;
    public int $majs = 0;

    /** @var array<int, array{ligne:int, motif:string}> */
    public array $rejets = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $numero = $i + 2; // +1 en-tête, +1 base 1
            $data = is_array($row) ? $row : $row->toArray();

            if ($motif = self::motifInvalidite($data)) {
                $this->rejets[] = ['ligne' => $numero, 'motif' => $motif];
                continue;
            }

            $sens = self::normaliserSens($data['sens'] ?? null);

            $titre = BudgetTitre::updateOrCreate(
                ['sens' => $sens, 'code' => trim((string) $data['titre_code'])],
                ['intitule' => (string) ($data['titre_intitule'] ?? $data['titre_code'])]
            );

            $article = BudgetArticle::updateOrCreate(
                ['titre_id' => $titre->id, 'code' => trim((string) $data['article_code'])],
                ['intitule' => (string) ($data['article_intitule'] ?? $data['article_code'])]
            );

            $paragraphe = BudgetParagraphe::updateOrCreate(
                ['article_id' => $article->id, 'code' => trim((string) $data['paragraphe_code'])],
                ['intitule' => (string) ($data['paragraphe_intitule'] ?? $data['paragraphe_code'])]
            );

            $rubrique = BudgetRubrique::updateOrCreate(
                ['paragraphe_id' => $paragraphe->id, 'code' => trim((string) $data['rubrique_code'])],
                ['intitule' => (string) ($data['rubrique_intitule'] ?? $data['rubrique_code'])]
            );

            $rubrique->wasRecentlyCreated ? $this->crees++ : $this->majs++;
            $this->lignesImportees++;
        }
    }

    /** Renvoie le motif de rejet d'une ligne, ou null si elle est valide. Réutilisé par l'aperçu. */
    public static function motifInvalidite(array $row): ?string
    {
        $manquants = [];
        foreach (['titre_code', 'article_code', 'paragraphe_code', 'rubrique_code'] as $champ) {
            if (trim((string) ($row[$champ] ?? '')) === '') {
                $manquants[] = $champ;
            }
        }
        if ($manquants !== []) {
            return 'Code(s) manquant(s) : '.implode(', ', $manquants);
        }

        return null;
    }

    /** Normalise le sens : accepte les 4 sens officiels, défaut « depense ». */
    public static function normaliserSens(?string $valeur): string
    {
        $sens = strtolower(trim((string) $valeur));

        return array_key_exists($sens, BudgetTitre::SENS) ? $sens : 'depense';
    }
}
