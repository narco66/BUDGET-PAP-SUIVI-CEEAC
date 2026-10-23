<?php

namespace App\Services\Rbm;

use App\Models\Activite;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\Mesure;
use App\Models\PerformanceIndicateur;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Source unique des calculs KPI / tendances pour les indicateurs IOV (phase P0). */
final class IndicateurPerformanceService
{
    /** @return array{taux_atteinte: float, feu: string} */
    public function calculerTauxEtFeu(Indicateur $indicateur, float $valeurRealisee): array
    {
        $cible = (float) ($indicateur->valeur_cible ?? 0);
        $taux = $cible > 0
            ? round($valeurRealisee / $cible * 100, 2)
            : ($valeurRealisee > 0 ? 100.0 : 0.0);

        $seuil = (float) ($indicateur->seuil_alerte ?? 50);
        $feu = match (true) {
            $taux >= 75 => 'vert',
            $taux >= $seuil => 'orange',
            default => 'rouge',
        };

        return ['taux_atteinte' => $taux, 'feu' => $feu];
    }

    public function ecartCible(Indicateur $indicateur, float $valeurRealisee): float
    {
        return round($valeurRealisee - (float) ($indicateur->valeur_cible ?? 0), 4);
    }

    /** @return array<string,mixed>|null */
    public function tendance(Indicateur $indicateur, string $periodeCourante): ?array
    {
        $mesures = $indicateur->mesures()
            ->where('statut', 'validee')
            ->orderBy('periode')
            ->get(['periode', 'valeur_realisee', 'taux_atteinte']);

        $courante = $mesures->firstWhere('periode', $periodeCourante);
        $precedente = $mesures->where('periode', '<', $periodeCourante)->last();

        if (! $courante || ! $precedente) {
            return null;
        }

        $valPrec = (float) $precedente->valeur_realisee;
        $valCour = (float) $courante->valeur_realisee;

        return [
            'periode_precedente' => $precedente->periode,
            'valeur_precedente' => $valPrec,
            'valeur_courante' => $valCour,
            'variation_abs' => round($valCour - $valPrec, 4),
            'variation_pct' => $valPrec > 0 ? round(($valCour / $valPrec - 1) * 100, 1) : null,
        ];
    }

    /** @return array<string,mixed> */
    public function syntheseIndicateur(Indicateur $indicateur): array
    {
        $derniere = $indicateur->mesures()
            ->where('statut', 'validee')
            ->orderByDesc('periode')
            ->first();

        return [
            'code' => $indicateur->code,
            'libelle' => $indicateur->libelle,
            'cible' => (float) ($indicateur->valeur_cible ?? 0),
            'derniere_periode' => $derniere?->periode,
            'valeur_realisee' => (float) ($derniere?->valeur_realisee ?? 0),
            'taux_atteinte' => (float) ($derniere?->taux_atteinte ?? 0),
            'feu' => $derniere?->feu,
            'tendance' => $derniere ? $this->tendance($indicateur, $derniere->periode) : null,
        ];
    }

    /** Répartition des feux (dernière mesure par indicateur). */
    public function feuxGlobaux(?int $exerciceId = null): array
    {
        $query = Mesure::query()
            ->select('mesures.feu', DB::raw('COUNT(*) as n'))
            ->whereNotNull('mesures.feu')
            ->joinSub(
                Mesure::query()
                    ->selectRaw('indicateur_id, MAX(id) as last_id')
                    ->groupBy('indicateur_id'),
                'last_m',
                fn ($join) => $join->on('mesures.id', '=', 'last_m.last_id')
            );

        if ($exerciceId) {
            $activiteIds = Activite::query()
                ->whereHas('sousProduit.produit.axe.pilier', fn ($p) => $p->where('exercice_id', $exerciceId))
                ->pluck('id');

            $query->whereHas('indicateur', fn ($i) => $i
                ->where('rbm_type', Activite::class)
                ->whereIn('rbm_id', $activiteIds));
        }

        $base = $query->groupBy('mesures.feu')->pluck('n', 'feu');

        return [
            'vert' => (int) ($base['vert'] ?? 0),
            'orange' => (int) ($base['orange'] ?? 0),
            'rouge' => (int) ($base['rouge'] ?? 0),
        ];
    }

    /** Historique des mesures validées d'un indicateur. */
    public function historiqueValide(Indicateur $indicateur): Collection
    {
        return $indicateur->mesures()
            ->where('statut', 'validee')
            ->orderBy('periode')
            ->get(['periode', 'valeur_realisee', 'taux_atteinte', 'feu', 'valide_le']);
    }

    /** Synthèses IOV pour tous les indicateurs d'un exercice (rapport évolution). */
    public function evolutionExercice(int $exerciceId): Collection
    {
        $exercice = Exercice::with([
            'piliers.indicateurs.mesures',
            'piliers.axes.indicateurs.mesures',
            'piliers.axes.produits.indicateurs.mesures',
            'piliers.axes.produits.sousProduits.indicateurs.mesures',
            'piliers.axes.produits.sousProduits.activites.indicateurs.mesures',
        ])->findOrFail($exerciceId);

        return $this->collecterIndicateurs($exercice)
            ->map(function (Indicateur $indicateur) {
                $syn = $this->syntheseIndicateur($indicateur);

                return array_merge($syn, [
                    'ecart_cible' => $this->ecartCible($indicateur, $syn['valeur_realisee']),
                    'variation_pct' => $syn['tendance']['variation_pct'] ?? null,
                ]);
            })
            ->sortBy('code')
            ->values();
    }

    private function collecterIndicateurs(Exercice $exercice): Collection
    {
        $all = collect();
        foreach ($exercice->piliers as $pilier) {
            $all = $all->merge($pilier->indicateurs);
            foreach ($pilier->axes as $axe) {
                $all = $all->merge($axe->indicateurs);
                foreach ($axe->produits as $produit) {
                    $all = $all->merge($produit->indicateurs);
                    foreach ($produit->sousProduits as $sp) {
                        $all = $all->merge($sp->indicateurs);
                        foreach ($sp->activites as $act) {
                            $all = $all->merge($act->indicateurs);
                        }
                    }
                }
            }
        }

        return $all->unique('id')->values();
    }

    public function capturerPerformance(Mesure $mesure, ?int $userId = null): PerformanceIndicateur
    {
        $mesure->loadMissing('indicateur');
        $indicateur = $mesure->indicateur;
        $annee = (int) substr((string) $mesure->periode, 0, 4);
        $exerciceId = $annee > 0 ? Exercice::firstWhere('annee', $annee)?->id : null;

        return PerformanceIndicateur::updateOrCreate(
            ['indicateur_id' => $indicateur->id, 'periode' => $mesure->periode],
            [
                'exercice_id' => $exerciceId,
                'valeur_cible' => $indicateur->valeur_cible,
                'valeur_realisee' => $mesure->valeur_realisee,
                'taux_atteinte' => $mesure->taux_atteinte,
                'feu' => $mesure->feu,
                'mesure_id' => $mesure->id,
                'capture_par' => $userId,
                'capture_le' => now(),
            ],
        );
    }

    /** Snapshots persistés pour un exercice. */
    public function performancesExercice(int $exerciceId): Collection
    {
        return PerformanceIndicateur::query()
            ->where('exercice_id', $exerciceId)
            ->with('indicateur:id,code,libelle')
            ->orderBy('periode')
            ->orderBy('indicateur_id')
            ->get();
    }

    public function capturerExercice(int $exerciceId, ?int $userId = null): int
    {
        $exercice = Exercice::findOrFail($exerciceId);
        $count = 0;

        Mesure::query()
            ->where('statut', 'validee')
            ->where('periode', 'like', $exercice->annee.'-%')
            ->each(function (Mesure $mesure) use ($userId, &$count) {
                $this->capturerPerformance($mesure, $userId);
                $count++;
            });

        return $count;
    }
}
