<?php

namespace App\Imports;

use App\Models\LigneBudgetaire;
use App\Models\SourceFinancement;
use App\Models\Structure;
use App\Services\Budget\NomenclatureResolver;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import des lignes budgétaires d'un exercice.
 * En-têtes : code, intitule, structure_code, rubrique_code, paragraphe_code, article_code, source_code, montant_ouvert.
 * Résolution rubrique composite (paragraphe/article) pour éviter les ambiguïtés.
 */
class LignesBudgetairesImport implements ToCollection, WithHeadingRow
{
    use Importable;

    public int $importees = 0;

    /** @var array<int,string> */
    public array $rejets = [];

    public function __construct(
        private readonly int $exerciceId,
        private readonly ?NomenclatureResolver $resolver = null,
    ) {
    }

    public function collection(Collection $rows): void
    {
        $resolver = $this->resolver ?? app(NomenclatureResolver::class);

        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $rubriqueCode = trim((string) ($row['rubrique_code'] ?? ''));
            if ($code === '' || $rubriqueCode === '') {
                continue;
            }

            $codes = [
                'rubrique_code' => $rubriqueCode,
                'paragraphe_code' => trim((string) ($row['paragraphe_code'] ?? '')),
                'article_code' => trim((string) ($row['article_code'] ?? '')),
                'titre_code' => trim((string) ($row['titre_code'] ?? '')),
            ];

            $rubrique = $resolver->resoudreRubrique($codes);
            if (! $rubrique) {
                $this->rejets[] = "Ligne {$code} : ".$resolver->messageErreurResolution($codes);
                continue;
            }

            $structure = Structure::firstWhere('code', trim((string) ($row['structure_code'] ?? '')));
            $source = SourceFinancement::firstWhere('code', trim((string) ($row['source_code'] ?? '')));

            LigneBudgetaire::updateOrCreateWithTrashed(
                ['exercice_id' => $this->exerciceId, 'code' => $code],
                [
                    'rubrique_id' => $rubrique->id,
                    'structure_id' => $structure?->id,
                    'source_financement_id' => $source?->id,
                    'intitule' => (string) ($row['intitule'] ?? $rubrique->intitule),
                    'montant_ouvert' => $this->montant($row['montant_ouvert'] ?? 0),
                ]
            );

            $this->importees++;
        }
    }

    private function montant(mixed $valeur): float
    {
        return (float) str_replace([' ', ' ', ','], ['', '', '.'], (string) $valeur);
    }
}
