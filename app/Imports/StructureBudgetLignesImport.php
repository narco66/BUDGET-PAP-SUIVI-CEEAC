<?php

namespace App\Imports;

use App\Models\Activite;
use App\Models\LigneBudgetaire;
use App\Models\SourceFinancement;
use App\Models\Structure;
use App\Services\Budget\NomenclatureResolver;
use App\Services\Budget\RbmBudgetSyncService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import structure budgétaire (module unifié) — réutilise la résolution nomenclature existante
 * + rattachement activité PAP optionnel. Mode dry-run pour prévisualisation.
 */
class StructureBudgetLignesImport implements ToCollection, WithHeadingRow
{
    use Importable;

    public const COLONNES_REQUISES = ['code', 'rubrique_code'];

    public const COLONNES_OPTIONNELLES = [
        'intitule', 'structure_code', 'activite_code', 'paragraphe_code', 'article_code',
        'titre_code', 'source_code', 'montant_ouvert',
    ];

    public int $importees = 0;

    /** @var array<int,array{ligne:int,motif:string}> */
    public array $rejets = [];

    /** @var array<int,array<string,mixed>> */
    public array $valides = [];

    public function __construct(
        private readonly int $exerciceId,
        private readonly bool $dryRun = false,
        private readonly ?NomenclatureResolver $resolver = null,
    ) {
    }

    public function collection(Collection $rows): void
    {
        $resolver = $this->resolver ?? app(NomenclatureResolver::class);
        $sync = app(RbmBudgetSyncService::class);

        foreach ($rows as $index => $row) {
            $numLigne = $index + 2;
            $data = is_array($row) ? $row : $row->toArray();
            $motif = self::motifInvalidite($data, $this->exerciceId, $resolver);

            if ($motif !== null) {
                if (count($this->rejets) < 100) {
                    $this->rejets[] = ['ligne' => $numLigne, 'motif' => $motif];
                }

                continue;
            }

            if ($this->dryRun) {
                if (count($this->valides) < 50) {
                    $this->valides[] = ['ligne' => $numLigne, 'code' => trim((string) $data['code'])];
                }

                continue;
            }

            $ligne = $this->persister($data, $resolver);
            $sync->synchroniserLigne($ligne);
            $this->importees++;
        }
    }

    /** @param  array<string,mixed>  $row */
    public static function motifInvalidite(array $row, int $exerciceId, ?NomenclatureResolver $resolver = null): ?string
    {
        $code = trim((string) ($row['code'] ?? ''));
        $rubriqueCode = trim((string) ($row['rubrique_code'] ?? ''));

        if ($code === '') {
            return 'Code ligne manquant.';
        }
        if ($rubriqueCode === '') {
            return 'Code rubrique manquant.';
        }

        $resolver ??= app(NomenclatureResolver::class);
        $codes = [
            'rubrique_code' => $rubriqueCode,
            'paragraphe_code' => trim((string) ($row['paragraphe_code'] ?? '')),
            'article_code' => trim((string) ($row['article_code'] ?? '')),
            'titre_code' => trim((string) ($row['titre_code'] ?? '')),
        ];

        if (! $resolver->resoudreRubrique($codes)) {
            return $resolver->messageErreurResolution($codes);
        }

        $structureCode = trim((string) ($row['structure_code'] ?? ''));
        if ($structureCode !== '' && ! Structure::where('code', $structureCode)->exists()) {
            return "Structure « {$structureCode} » introuvable.";
        }

        $activiteCode = trim((string) ($row['activite_code'] ?? ''));
        if ($activiteCode !== '') {
            $activite = Activite::where('code', $activiteCode)->first();
            if (! $activite) {
                return "Activité PAP « {$activiteCode} » introuvable.";
            }
            if ($structureCode !== '') {
                $structure = Structure::firstWhere('code', $structureCode);
                if ($structure && (int) $activite->structure_id !== (int) $structure->id) {
                    return "L'activité {$activiteCode} n'appartient pas à la structure {$structureCode}.";
                }
            }
            $dansExercice = Activite::where('code', $activiteCode)
                ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
                ->exists();
            if (! $dansExercice) {
                return "L'activité {$activiteCode} n'appartient pas à l'exercice sélectionné.";
            }
        }

        $sourceCode = trim((string) ($row['source_code'] ?? ''));
        if ($sourceCode !== '' && ! SourceFinancement::where('code', $sourceCode)->exists()) {
            return "Source « {$sourceCode} » introuvable.";
        }

        $montant = self::montant($row['montant_ouvert'] ?? 0);
        if ($montant < 0) {
            return 'Montant ouvert invalide.';
        }

        return null;
    }

    /** @param  array<string,mixed>  $row */
    private function persister(array $row, NomenclatureResolver $resolver): LigneBudgetaire
    {
        $codes = [
            'rubrique_code' => trim((string) $row['rubrique_code']),
            'paragraphe_code' => trim((string) ($row['paragraphe_code'] ?? '')),
            'article_code' => trim((string) ($row['article_code'] ?? '')),
            'titre_code' => trim((string) ($row['titre_code'] ?? '')),
        ];
        $rubrique = $resolver->resoudreRubrique($codes);
        $structure = Structure::firstWhere('code', trim((string) ($row['structure_code'] ?? '')));
        $source = SourceFinancement::firstWhere('code', trim((string) ($row['source_code'] ?? '')));
        $activite = Activite::firstWhere('code', trim((string) ($row['activite_code'] ?? '')));

        return LigneBudgetaire::updateOrCreateWithTrashed(
            ['exercice_id' => $this->exerciceId, 'code' => trim((string) $row['code'])],
            [
                'rubrique_id' => $rubrique->id,
                'structure_id' => $structure?->id,
                'source_financement_id' => $source?->id,
                'activite_id' => $activite?->id,
                'intitule' => (string) ($row['intitule'] ?? $rubrique->intitule),
                'montant_ouvert' => self::montant($row['montant_ouvert'] ?? 0),
                'actif' => true,
            ]
        );
    }

    private static function montant(mixed $valeur): float
    {
        return (float) str_replace([' ', ' ', ','], ['', '', '.'], (string) $valeur);
    }
}
