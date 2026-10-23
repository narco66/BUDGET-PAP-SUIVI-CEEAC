<?php

namespace App\Imports;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\LigneBudgetaire;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Services\Budget\RbmBudgetSyncService;
use App\Services\Pap\PapReferentielService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import du détail du PAP (chaîne RBM) pour un exercice.
 * En-têtes attendus : pilier_code, pilier_intitule, axe_code, axe_intitule,
 * produit_code, produit_intitule, sous_produit_code, sous_produit_intitule,
 * activite_code, activite_intitule, montant_ceeac, montant_ptf.
 * Liaison auto optionnelle : ligne_code (code de la ligne budgétaire).
 */
class PapImport implements ToCollection, WithHeadingRow
{
    use Importable;

    public int $piliers = 0;
    public int $axes = 0;
    public int $produits = 0;
    public int $sousProduits = 0;
    public int $activites = 0;
    public int $liaisonsBudget = 0;

    public function __construct(
        private readonly int $exerciceId,
        private readonly ?RbmBudgetSyncService $budgetSync = null,
        private readonly ?PapReferentielService $papReferentiel = null,
    ) {
    }

    public function collection(Collection $rows): void
    {
        $sync = $this->budgetSync ?? app(RbmBudgetSyncService::class);
        $papRef = $this->papReferentiel ?? app(PapReferentielService::class);
        $pap = Pap::where('exercice_id', $this->exerciceId)->first();

        foreach ($rows as $row) {
            $pCode = $this->v($row['pilier_code'] ?? null);
            if ($pCode === '') {
                continue;
            }

            $pilier = Pilier::firstOrCreate(
                ['exercice_id' => $this->exerciceId, 'code' => $pCode],
                ['intitule' => $this->v($row['pilier_intitule'] ?? null) ?: $pCode]
            );
            if ($pilier->wasRecentlyCreated) {
                $this->piliers++;
            }
            if ($pap) {
                $papRef->lierPilier($pilier, $pap);
            }

            $axe = $this->niveau(Axe::class, ['pilier_id' => $pilier->id], $row, 'axe', $this->axes);
            if (! $axe) {
                continue;
            }

            $produit = $this->niveau(Produit::class, ['axe_id' => $axe->id], $row, 'produit', $this->produits);
            if (! $produit) {
                continue;
            }

            $sp = $this->niveau(SousProduit::class, ['produit_id' => $produit->id], $row, 'sous_produit', $this->sousProduits);
            if (! $sp) {
                continue;
            }

            $aCode = $this->v($row['activite_code'] ?? null);
            if ($aCode === '') {
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
                ]
            );
            if ($act->wasRecentlyCreated) {
                $this->activites++;
            }

            $this->lierLigneBudgetaire($act, $row, $aCode);
            $sync->synchroniserActivite($act->fresh());
            if ($act->ligne_budgetaire_id) {
                $this->liaisonsBudget++;
            }
        }

        $papRef->synchroniserLiensPiliers($this->exerciceId);
    }

    private function lierLigneBudgetaire(Activite $act, $row, string $aCode): void
    {
        $ligneCode = $this->v($row['ligne_code'] ?? null);
        if ($ligneCode === '') {
            $ligneCode = $aCode;
        }

        $ligne = LigneBudgetaire::where('exercice_id', $this->exerciceId)
            ->where('code', $ligneCode)
            ->first();

        if ($ligne && (int) $act->ligne_budgetaire_id !== (int) $ligne->id) {
            $act->update(['ligne_budgetaire_id' => $ligne->id]);
        }
    }

    /** Crée/retrouve un niveau intermédiaire (axe/produit/sous_produit) si son code est présent. */
    private function niveau(string $modele, array $parent, $row, string $prefixe, int &$compteur)
    {
        $code = $this->v($row[$prefixe.'_code'] ?? null);
        if ($code === '') {
            return null;
        }
        $item = $modele::firstOrCreate(
            [...$parent, 'code' => $code],
            ['intitule' => $this->v($row[$prefixe.'_intitule'] ?? null) ?: $code]
        );
        $item->wasRecentlyCreated && $compteur++;

        return $item;
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
