<?php

namespace App\Imports;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Indicateur;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Rbm\Niveaux;
use App\Services\Rbm\IndicateurIntegriteService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import contrôlé des indicateurs RBM (phase 3).
 * Colonnes : pilier_code, axe_code?, produit_code?, sous_produit_code?, niveau, noeud_code,
 * indicateur_code, libelle, type, unite, periodicite, valeur_reference, valeur_cible, seuil_alerte.
 */
class IndicateursImport implements ToCollection, WithHeadingRow
{
    use Importable;

    public int $importes = 0;
    public int $lignesTraitees = 0;

    /** @var array<int,string> */
    public array $rejets = [];

    public function __construct(
        private readonly int $exerciceId,
        private readonly ?IndicateurIntegriteService $integrite = null,
    ) {
    }

    public function collection(Collection $rows): void
    {
        $integrite = $this->integrite ?? app(IndicateurIntegriteService::class);

        foreach ($rows as $index => $row) {
            $ligne = $index + 2;
            $codeInd = $this->v($row['indicateur_code'] ?? null);
            if ($codeInd === '') {
                continue;
            }

            $this->lignesTraitees++;
            $niveau = $this->v($row['niveau'] ?? null);
            if (! Niveaux::existe($niveau)) {
                $this->rejets[] = "Ligne {$ligne} : niveau « {$niveau} » invalide.";

                continue;
            }

            $noeud = $this->resoudreNoeud($row, $niveau, $ligne);
            if (! $noeud) {
                continue;
            }

            $type = $this->v($row['type'] ?? null) ?: 'operationnel';
            if (! in_array($type, ['strategique', 'operationnel', 'financier', 'impact'], true)) {
                $this->rejets[] = "Ligne {$ligne} : type « {$type} » invalide.";

                continue;
            }

            $periodicite = $this->v($row['periodicite'] ?? null) ?: 'trimestriel';
            if (! in_array($periodicite, ['mensuel', 'trimestriel', 'semestriel', 'annuel', 'pluriannuel'], true)) {
                $this->rejets[] = "Ligne {$ligne} : périodicité « {$periodicite} » invalide.";

                continue;
            }

            $typeIndicateurId = $integrite->resoudreTypeIndicateurId($type);
            $uniteMesureId = $integrite->resoudreUniteMesureId($this->v($row['unite'] ?? null) ?: null);
            if (! $typeIndicateurId || ! $uniteMesureId) {
                $this->rejets[] = "Ligne {$ligne} : catalogues type/unité indisponibles.";

                continue;
            }

            $uniteLegacy = $this->v($row['unite'] ?? null) ?: null;

            Indicateur::updateOrCreate(
                ['code' => $codeInd],
                [
                    'rbm_type' => $noeud::class,
                    'rbm_id' => $noeud->id,
                    'libelle' => $this->v($row['libelle'] ?? null) ?: $codeInd,
                    'type' => $type,
                    'type_indicateur_id' => $typeIndicateurId,
                    'unite' => $uniteLegacy,
                    'unite_mesure_id' => $uniteMesureId,
                    'periodicite' => $periodicite,
                    'valeur_reference' => $this->num($row['valeur_reference'] ?? null),
                    'valeur_cible' => $this->num($row['valeur_cible'] ?? null),
                    'seuil_alerte' => $this->num($row['seuil_alerte'] ?? null),
                    'methode_calcul' => $this->v($row['methode_calcul'] ?? null) ?: null,
                    'source_verification' => $this->v($row['source_verification'] ?? null) ?: null,
                    'statut' => 'actif',
                ]
            );
            $this->importes++;
        }
    }

    private function resoudreNoeud($row, string $niveau, int $ligne): mixed
    {
        $pilierCode = $this->v($row['pilier_code'] ?? null);
        if ($pilierCode === '') {
            $this->rejets[] = "Ligne {$ligne} : pilier_code manquant.";

            return null;
        }

        $pilier = Pilier::where('exercice_id', $this->exerciceId)->where('code', $pilierCode)->first();
        if (! $pilier) {
            $this->rejets[] = "Ligne {$ligne} : pilier « {$pilierCode} » introuvable.";

            return null;
        }

        $noeudCode = $this->v($row['noeud_code'] ?? null) ?: $pilierCode;

        return match ($niveau) {
            'pilier' => $pilier->code === $noeudCode ? $pilier : $this->rejeter($ligne, "pilier « {$noeudCode} » incohérent."),
            'axe' => Axe::where('pilier_id', $pilier->id)->where('code', $noeudCode)->first()
                ?? $this->rejeter($ligne, "axe « {$noeudCode} » introuvable."),
            'produit' => $this->trouverProduit($pilier, $row, $noeudCode, $ligne),
            'sous_produit' => $this->trouverSousProduit($pilier, $row, $noeudCode, $ligne),
            'activite' => $this->trouverActivite($pilier, $row, $noeudCode, $ligne),
            default => null,
        };
    }

    private function trouverProduit(Pilier $pilier, $row, string $code, int $ligne): ?Produit
    {
        $axeCode = $this->v($row['axe_code'] ?? null);
        $query = Produit::whereHas('axe', function ($q) use ($pilier, $axeCode) {
            $q->where('pilier_id', $pilier->id);
            if ($axeCode !== '') {
                $q->where('code', $axeCode);
            }
        })->where('code', $code);

        return $query->first() ?? $this->rejeter($ligne, "produit « {$code} » introuvable.");
    }

    private function trouverSousProduit(Pilier $pilier, $row, string $code, int $ligne): ?SousProduit
    {
        $produitCode = $this->v($row['produit_code'] ?? null);
        $query = SousProduit::whereHas('produit.axe', fn ($q) => $q->where('pilier_id', $pilier->id))
            ->when($produitCode !== '', fn ($q) => $q->whereHas('produit', fn ($p) => $p->where('code', $produitCode)))
            ->where('code', $code);

        return $query->first() ?? $this->rejeter($ligne, "sous-produit « {$code} » introuvable.");
    }

    private function trouverActivite(Pilier $pilier, $row, string $code, int $ligne): ?Activite
    {
        $spCode = $this->v($row['sous_produit_code'] ?? null);
        $query = Activite::whereHas('sousProduit.produit.axe', fn ($q) => $q->where('pilier_id', $pilier->id))
            ->when($spCode !== '', fn ($q) => $q->whereHas('sousProduit', fn ($s) => $s->where('code', $spCode)))
            ->where('code', $code);

        return $query->first() ?? $this->rejeter($ligne, "activité « {$code} » introuvable.");
    }

    private function rejeter(int $ligne, string $msg): null
    {
        $this->rejets[] = "Ligne {$ligne} : {$msg}";

        return null;
    }

    private function v(mixed $x): string
    {
        return trim((string) $x);
    }

    private function num(mixed $x): ?float
    {
        if ($x === null || $x === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $x);
    }
}
