<?php

namespace App\Services\Rbm;

use App\Models\Activite;
use App\Models\ExpressionBesoin;
use App\Models\Tache;
use App\Services\Depense\ImputationPapService;
use App\Support\Montant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Règle budgétaire des tâches d'une ligne PAP. Une ligne peut faire l'objet de plusieurs activités
 * (une par expression de besoin) ; chaque activité a zéro, une ou plusieurs tâches :
 * - le total des tâches d'une activité est égal au montant de l'activité (montant estimé du besoin) ;
 * - le cumul des tâches d'une ligne ne dépasse jamais le total de la ligne.
 */
class TacheBudgetService
{
    /** Tolérance d'arrondi (XAF). */
    private const TOLERANCE = 0.5;

    public function totalTaches(Activite $activite, ?int $ignorerTacheId = null, ?int $ignorerBesoinId = null): float
    {
        return (float) Tache::query()->where('activite_id', $activite->id)
            ->when($ignorerTacheId, fn ($q) => $q->whereKeyNot($ignorerTacheId))
            ->when($ignorerBesoinId, fn ($q) => $q->where(fn ($b) => $b->whereNull('expression_besoin_id')->orWhere('expression_besoin_id', '!=', $ignorerBesoinId)))
            ->sum('montant');
    }

    /** Saisie d'une tâche : le cumul des tâches de la ligne ne dépasse pas le total de la ligne. */
    public function assertPlafond(Activite $activite, float $montantTache, ?int $tacheId = null): void
    {
        $total = $this->totalTaches($activite, $tacheId) + $montantTache;
        if ($total - (float) $activite->montant_total > self::TOLERANCE) {
            throw new RuntimeException(sprintf(
                'Le cumul des tâches (%s XAF) dépasserait le total de la ligne %s (%s XAF).',
                $this->fmt($total), ImputationPapService::codePap($activite), $this->fmt((float) $activite->montant_total),
            ));
        }
    }

    /** Modification du total d'une ligne : il ne peut pas descendre sous le cumul de ses tâches. */
    public function assertTotalLigne(Activite $activite, float $nouveauTotal): void
    {
        $taches = $this->totalTaches($activite);
        if ($taches > 0 && $taches - $nouveauTotal > self::TOLERANCE) {
            throw new RuntimeException(sprintf(
                'Le total de la ligne (%s XAF) ne peut pas être inférieur au cumul de ses tâches (%s XAF) : ajustez d\'abord les tâches.',
                $this->fmt($nouveauTotal), $this->fmt($taches),
            ));
        }
    }

    /**
     * Contrôle des tâches d'une activité saisies comme sous-lignes d'une expression de besoin.
     *
     * @param  list<array{intitule?: string, montant?: mixed}>  $lignes
     */
    public function anomalieSousLignes(Activite $activite, array $lignes, float $montantActivite, ?int $besoinId = null): ?string
    {
        $total = array_sum(array_map(fn ($l) => (float) ($l['montant'] ?? 0), $lignes));
        if (abs($total - $montantActivite) > self::TOLERANCE) {
            return sprintf(
                'Le total des tâches saisies (%s XAF) doit être égal au montant de l\'activité (montant estimé : %s XAF).',
                $this->fmt($total), $this->fmt($montantActivite),
            );
        }
        $cumul = $this->totalTaches($activite, null, $besoinId) + $total;
        if ($cumul - (float) $activite->montant_total > self::TOLERANCE) {
            return sprintf(
                'Le cumul des tâches de la ligne %s (%s XAF, dont %s XAF pour cette activité) dépasserait le total de la ligne (%s XAF).',
                ImputationPapService::codePap($activite), $this->fmt($cumul), $this->fmt($total), $this->fmt((float) $activite->montant_total),
            );
        }

        return null;
    }

    /**
     * Enregistre les tâches de l'activité exprimée par le besoin (remplace celles qu'il portait déjà).
     * Codes {code PAP}.n, numérotés à la suite des tâches existantes de la ligne.
     *
     * @param  list<array{intitule: string, montant: mixed, responsable_user_id?: mixed}>  $lignes
     * @return Collection<int, Tache>
     */
    public function enregistrerSousLignes(Activite $activite, ExpressionBesoin $besoin, array $lignes, ?int $userId = null): Collection
    {
        if ($message = $this->anomalieSousLignes($activite, $lignes, (float) $besoin->montant_estime, $besoin->id)) {
            throw new RuntimeException($message);
        }
        $prefixe = ImputationPapService::codePap($activite);

        return DB::transaction(function () use ($activite, $besoin, $lignes, $userId, $prefixe) {
            Tache::query()->where('expression_besoin_id', $besoin->id)->get()->each->delete();
            $rang = (int) Tache::withTrashed()->where('activite_id', $activite->id)->max('ordre');

            return collect(array_values($lignes))->map(function ($l) use ($activite, $besoin, $userId, $prefixe, &$rang) {
                $rang++;

                return Tache::create([
                    'activite_id' => $activite->id,
                    'expression_besoin_id' => $besoin->id,
                    'code' => $prefixe.'.'.$rang,
                    'intitule' => trim((string) $l['intitule']),
                    'montant' => Montant::api((float) $l['montant']),
                    'responsable_user_id' => isset($l['responsable_user_id']) ? (int) $l['responsable_user_id'] : null,
                    'statut' => 'a_faire',
                    'ordre' => $rang,
                    'created_by' => $userId,
                ]);
            });
        });
    }

    private function fmt(float $n): string
    {
        return number_format($n, 0, ',', ' ');
    }
}
