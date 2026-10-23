<?php

namespace App\Services\Institution;

use App\Models\Structure;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/** Import contrôlé du référentiel structures (phase A8). */
class InstitutionImportService
{
    /** @var array<string, int> */
    private const ORDRE_TYPES = [
        'presidence' => 1,
        'vice_presidence' => 2,
        'secretariat_general' => 3,
        'departement' => 4,
        'commissariat' => 5,
        'direction' => 6,
        'cellule' => 7,
        'service' => 8,
    ];

    public function __construct(private readonly StructureHierarchieService $hierarchie)
    {
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{crees:int,mis_a_jour:int,ignorees:int,erreurs:list<string>}
     */
    public function importerStructures(Collection $rows): array
    {
        $stats = ['crees' => 0, 'mis_a_jour' => 0, 'ignorees' => 0, 'erreurs' => []];

        $lignes = $rows
            ->map(fn ($row, $index) => ['index' => $index + 2, 'data' => $this->normaliserLigne($row)])
            ->filter(fn ($l) => $l['data']['code'] !== '')
            ->sortBy(fn ($l) => self::ORDRE_TYPES[$l['data']['type']] ?? 99)
            ->values();

        foreach ($lignes as $ligne) {
            $numero = $ligne['index'];
            $data = $ligne['data'];

            if ($data['type'] === '') {
                $stats['erreurs'][] = "Ligne {$numero} : type obligatoire.";
                continue;
            }

            if ($data['libelle'] === '') {
                $stats['erreurs'][] = "Ligne {$numero} ({$data['code']}) : libelle obligatoire.";
                continue;
            }

            if (! array_key_exists($data['type'], self::ORDRE_TYPES)) {
                $stats['erreurs'][] = "Ligne {$numero} : type « {$data['type']} » inconnu.";
                continue;
            }

            $parentId = null;
            if ($data['parent_code'] !== '') {
                $parent = Structure::where('code', $data['parent_code'])->first();
                if (! $parent) {
                    $stats['erreurs'][] = "Ligne {$numero} : parent « {$data['parent_code']} » introuvable.";
                    continue;
                }
                $parentId = $parent->id;
            }

            $existant = Structure::where('code', $data['code'])->first();
            $erreur = $this->hierarchie->valider($data['type'], $parentId, $existant?->id);
            if ($erreur) {
                $stats['erreurs'][] = "Ligne {$numero} ({$data['code']}) : {$erreur}";
                continue;
            }

            $responsableId = null;
            if ($data['responsable_email'] !== '') {
                $responsableId = User::where('email', $data['responsable_email'])->value('id');
                if (! $responsableId) {
                    $stats['erreurs'][] = "Ligne {$numero} ({$data['code']}) : responsable « {$data['responsable_email']} » introuvable.";
                    continue;
                }
            }

            $payload = [
                'libelle' => $data['libelle'],
                'sigle' => $data['sigle'] ?: null,
                'type' => $data['type'],
                'parent_id' => $parentId,
                'description' => $data['description'] ?: null,
                'actif' => $data['actif'],
                'ordre' => $data['ordre'],
                'niveau' => $this->calculerNiveau($parentId),
                'responsable_user_id' => $responsableId,
            ];

            if ($existant) {
                $existant->update($payload);
                $stats['mis_a_jour']++;
            } else {
                Structure::create(['code' => $data['code'], ...$payload]);
                $stats['crees']++;
            }
        }

        if ($stats['erreurs'] !== [] && $stats['crees'] === 0 && $stats['mis_a_jour'] === 0) {
            throw new InvalidArgumentException(implode("\n", array_slice($stats['erreurs'], 0, 5)));
        }

        return $stats;
    }

    /** @return array<string, string> */
    public function entetesModele(): array
    {
        return [
            'code' => 'CODE-STRUCT',
            'libelle' => 'Libelle structure',
            'sigle' => 'SIGLE',
            'type' => 'departement',
            'parent_code' => 'PARENT-CODE',
            'responsable_email' => 'responsable@ceeac.int',
            'description' => 'Description optionnelle',
            'actif' => 'oui',
            'ordre' => '1',
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function normaliserLigne(array $row): array
    {
        $actifRaw = strtolower(trim((string) ($row['actif'] ?? 'oui')));

        return [
            'code' => strtoupper(trim((string) ($row['code'] ?? ''))),
            'libelle' => trim((string) ($row['libelle'] ?? '')),
            'sigle' => trim((string) ($row['sigle'] ?? '')),
            'type' => strtolower(trim((string) ($row['type'] ?? ''))),
            'parent_code' => strtoupper(trim((string) ($row['parent_code'] ?? ''))),
            'responsable_email' => strtolower(trim((string) ($row['responsable_email'] ?? ''))),
            'description' => trim((string) ($row['description'] ?? '')),
            'actif' => ! in_array($actifRaw, ['non', '0', 'false', 'inactif'], true),
            'ordre' => (int) ($row['ordre'] ?? 0),
        ];
    }

    private function calculerNiveau(?int $parentId): int
    {
        if (! $parentId) {
            return 0;
        }

        return (int) Structure::whereKey($parentId)->value('niveau') + 1;
    }
}
