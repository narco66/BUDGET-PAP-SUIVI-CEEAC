<?php

namespace App\Services\Budget;

use App\Models\Activite;
use App\Models\LigneBudgetaire;
use App\Models\SourceFinancement;
use App\Support\AuditLogger;

/** Synchronise la liaison bidirectionnelle Activité RBM ↔ Ligne budgétaire + ventilation CEEAC/PTF. */
class RbmBudgetSyncService
{
    public function __construct(private readonly LigneFinancementService $financement)
    {
    }

    /** Canon : la ligne budgétaire porte activite_id ; l'activité porte ligne_budgetaire_id. */
    public function synchroniserActivite(Activite $activite): void
    {
        $ligneId = $activite->ligne_budgetaire_id;

        if (! $ligneId) {
            LigneBudgetaire::where('activite_id', $activite->id)->update(['activite_id' => null]);

            return;
        }

        $ligne = LigneBudgetaire::find($ligneId);
        if (! $ligne) {
            return;
        }

        LigneBudgetaire::where('activite_id', $activite->id)
            ->where('id', '!=', $ligne->id)
            ->update(['activite_id' => null]);

        if ((int) $ligne->activite_id !== (int) $activite->id) {
            $ligne->update(['activite_id' => $activite->id]);
            AuditLogger::enregistrer('rbm_liaison_budget', $ligne, null, [
                'activite_id' => $activite->id,
                'activite_code' => $activite->code,
            ]);
        }

        $this->propagerVentilationFinanciere($ligne->fresh(), $activite);
    }

    /** Depuis le module Budget : rattache une activité à la ligne. */
    public function synchroniserLigne(LigneBudgetaire $ligne): void
    {
        if (! $ligne->activite_id) {
            Activite::where('ligne_budgetaire_id', $ligne->id)->update(['ligne_budgetaire_id' => null]);

            return;
        }

        $activite = Activite::find($ligne->activite_id);
        if (! $activite) {
            return;
        }

        Activite::where('ligne_budgetaire_id', $ligne->id)
            ->where('id', '!=', $activite->id)
            ->update(['ligne_budgetaire_id' => null]);

        if ((int) $activite->ligne_budgetaire_id !== (int) $ligne->id) {
            $activite->update(['ligne_budgetaire_id' => $ligne->id]);
            AuditLogger::enregistrer('budget_liaison_rbm', $ligne, null, [
                'activite_id' => $activite->id,
                'activite_code' => $activite->code,
            ]);
        }

        $this->propagerVentilationFinanciere($ligne->fresh(), $activite);
    }

    /** Réplique montant_ceeac / montant_ptf de l'activité vers la ventilation multi-sources de la ligne. */
    public function propagerVentilationFinanciere(LigneBudgetaire $ligne, Activite $activite): void
    {
        $ceeac = (float) $activite->montant_ceeac;
        $ptf = (float) $activite->montant_ptf;

        if ($ceeac <= 0 && $ptf <= 0) {
            return;
        }

        $alloue = (float) $ligne->montant_alloue;
        if ($alloue <= 0) {
            return;
        }

        [$ceeac, $ptf] = $this->ajusterMontantsVentilation($ceeac, $ptf, $alloue);

        $sources = [];
        $sourceCeeac = SourceFinancement::query()->where('type', 'interne_ceeac')->where('actif', true)->orderBy('id')->first();
        $sourcePtf = $this->resoudreSourcePtf($ligne);

        if ($ceeac > 0 && $sourceCeeac) {
            $sources[] = [
                'source_financement_id' => $sourceCeeac->id,
                'montant' => $ceeac,
                'est_principale' => $ceeac >= $ptf,
            ];
        }

        if ($ptf > 0 && $sourcePtf) {
            $sources[] = [
                'source_financement_id' => $sourcePtf->id,
                'montant' => $ptf,
                'est_principale' => $ptf > $ceeac,
            ];
        }

        if ($sources === []) {
            return;
        }

        try {
            $this->financement->synchroniser($ligne, $sources);
            AuditLogger::enregistrer('rbm_ventilation_financiere', $ligne, null, [
                'activite_id' => $activite->id,
                'montant_ceeac' => $ceeac,
                'montant_ptf' => $ptf,
            ]);
        } catch (\RuntimeException) {
            // Ne bloque pas la sync RBM si la ventilation est incohérente (signalée par budget:audit)
        }
    }

    /** @return array{0:float,1:float} Répartit CEEAC/PTF pour couvrir exactement le crédit alloué. */
    private function ajusterMontantsVentilation(float $ceeac, float $ptf, float $alloue): array
    {
        $total = $ceeac + $ptf;
        if ($total <= 0) {
            return [0.0, 0.0];
        }

        if (abs($total - $alloue) <= 0.05) {
            return [$ceeac, $ptf];
        }

        $ratio = $alloue / $total;
        $ceeac = round($ceeac * $ratio, 2);
        $ptf = round($ptf * $ratio, 2);
        $delta = round($alloue - ($ceeac + $ptf), 2);

        if (abs($delta) >= 0.01) {
            if ($ptf > 0) {
                $ptf = round($ptf + $delta, 2);
            } else {
                $ceeac = round($ceeac + $delta, 2);
            }
        }

        return [$ceeac, $ptf];
    }

    private function resoudreSourcePtf(LigneBudgetaire $ligne): ?SourceFinancement
    {
        $ligne->loadMissing('sourceFinancement');

        if ($ligne->sourceFinancement?->type === 'externe_ptf') {
            return $ligne->sourceFinancement;
        }

        return SourceFinancement::query()
            ->where('type', 'externe_ptf')
            ->where('actif', true)
            ->orderBy('id')
            ->first();
    }
}
