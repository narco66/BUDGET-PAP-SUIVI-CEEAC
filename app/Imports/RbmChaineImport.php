<?php

namespace App\Imports;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Tache;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import contrôlé de la chaîne RBM/GAR (Module 2 — phase 2).
 * Étend PapImport avec validation, rejets explicites et support optionnel des tâches.
 */
class RbmChaineImport implements ToCollection, WithHeadingRow
{
    use Importable;

    public int $piliers = 0;
    public int $axes = 0;
    public int $produits = 0;
    public int $sousProduits = 0;
    public int $activites = 0;
    public int $taches = 0;
    public int $lignesTraitees = 0;

    /** @var array<int,string> */
    public array $rejets = [];

    /** @var array<string,bool> */
    private array $codesVus = [];

    public function __construct(private readonly int $exerciceId)
    {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $ligne = $index + 2; // en-tête ligne 1
            $pCode = $this->v($row['pilier_code'] ?? null);
            if ($pCode === '') {
                continue;
            }

            $this->lignesTraitees++;

            if ($this->codeDupliqueFichier('pilier', $pCode, $ligne)) {
                continue;
            }

            $pilier = Pilier::firstOrCreate(
                ['exercice_id' => $this->exerciceId, 'code' => $pCode],
                ['intitule' => $this->v($row['pilier_intitule'] ?? null) ?: $pCode, 'statut' => 'actif']
            );
            $pilier->wasRecentlyCreated && $this->piliers++;

            $axe = $this->niveau(Axe::class, ['pilier_id' => $pilier->id], $row, 'axe', $this->axes, $ligne);
            if (! $axe) {
                continue;
            }

            $produit = $this->niveau(Produit::class, ['axe_id' => $axe->id], $row, 'produit', $this->produits, $ligne);
            if (! $produit) {
                continue;
            }

            $sp = $this->niveau(SousProduit::class, ['produit_id' => $produit->id], $row, 'sous_produit', $this->sousProduits, $ligne);
            if (! $sp) {
                continue;
            }

            $aCode = $this->v($row['activite_code'] ?? null);
            if ($aCode === '') {
                $this->rejets[] = "Ligne {$ligne} : activite_code manquant.";

                continue;
            }

            $ceeac = $this->montant($row['montant_ceeac'] ?? 0);
            $ptf = $this->montant($row['montant_ptf'] ?? 0);

            $act = Activite::updateOrCreate(
                ['sous_produit_id' => $sp->id, 'code' => $aCode],
                [
                    'produit_id' => $produit->id,
                    'intitule' => $this->v($row['activite_intitule'] ?? null) ?: $aCode,
                    'montant_ceeac' => $ceeac,
                    'montant_ptf' => $ptf,
                    'montant_total' => $ceeac + $ptf,
                    'statut' => 'actif',
                ]
            );
            $act->wasRecentlyCreated && $this->activites++;

            $tCode = $this->v($row['tache_code'] ?? null);
            if ($tCode !== '') {
                $tache = Tache::updateOrCreate(
                    ['activite_id' => $act->id, 'code' => $tCode],
                    [
                        'intitule' => $this->v($row['tache_intitule'] ?? null) ?: $tCode,
                        'montant' => $this->montant($row['tache_montant'] ?? 0),
                        'statut' => $this->v($row['tache_statut'] ?? null) ?: 'a_faire',
                        'statut_referentiel' => 'actif',
                    ]
                );
                $tache->wasRecentlyCreated && $this->taches++;
            }
        }
    }

    /** @param  class-string  $modele */
    private function niveau(string $modele, array $parent, $row, string $prefixe, int &$compteur, int $ligne)
    {
        $code = $this->v($row[$prefixe.'_code'] ?? null);
        if ($code === '') {
            $this->rejets[] = "Ligne {$ligne} : {$prefixe}_code manquant (chaîne incomplète).";

            return null;
        }

        $cleParent = array_key_first($parent);
        $cle = "{$prefixe}:{$parent[$cleParent]}:{$code}";
        if (isset($this->codesVus[$cle])) {
            return $modele::firstWhere([...$parent, 'code' => $code]);
        }
        $this->codesVus[$cle] = true;

        $item = $modele::firstOrCreate(
            [...$parent, 'code' => $code],
            ['intitule' => $this->v($row[$prefixe.'_intitule'] ?? null) ?: $code, 'statut' => 'actif']
        );
        $item->wasRecentlyCreated && $compteur++;

        return $item;
    }

    private function codeDupliqueFichier(string $niveau, string $code, int $ligne): bool
    {
        $cle = "{$niveau}:{$this->exerciceId}:{$code}";
        if (isset($this->codesVus[$cle])) {
            $this->rejets[] = "Ligne {$ligne} : code pilier « {$code} » dupliqué dans le fichier.";

            return true;
        }
        $this->codesVus[$cle] = true;

        return false;
    }

    private function v(mixed $x): string
    {
        return trim((string) $x);
    }

    private function montant(mixed $valeur): float
    {
        return (float) str_replace([' ', ' ', ','], ['', '', '.'], (string) $valeur);
    }
}
