<?php

namespace App\Services\Suivi;

/** Source unique des scores Suivi-Évaluation (phase P0). */
final class SuiviEvaluationCalculator
{
    /** @var array<string, float> */
    public const POIDS_COMPOSANTES = [
        'physique' => 0.30,
        'iov' => 0.25,
        'recommandations' => 0.20,
        'formations' => 0.10,
        'conformite' => 0.15,
    ];

    public static function taux(float $numerateur, float $denominateur): float
    {
        return $denominateur > 0 ? round($numerateur / $denominateur * 100, 1) : 0.0;
    }

    /** Score IOV depuis répartition feux (vert=100, orange=50, rouge=0). */
    public static function scoreIov(int $vert, int $orange, int $rouge): float
    {
        $total = $vert + $orange + $rouge;

        return $total > 0
            ? round(($vert * 100 + $orange * 50) / $total, 1)
            : 0.0;
    }

    /** Score physique : couverture PAP et taux moyen des collectes validées. */
    public static function scorePhysique(float $tauxCouverture, ?float $tauxMoyenCollectes): float
    {
        $physique = $tauxMoyenCollectes ?? $tauxCouverture;

        return round($tauxCouverture * 0.4 + $physique * 0.6, 1);
    }

    /** Score recommandations : exécution moyenne et taux de clôture positive. */
    public static function scoreRecommandations(float $tauxExecutionMoyen, float $tauxRealisees): float
    {
        return round($tauxExecutionMoyen * 0.6 + $tauxRealisees * 0.4, 1);
    }

    /** Score formations : réalisation pondérée par satisfaction si disponible. */
    public static function scoreFormations(float $tauxRealisation, ?float $satisfactionMoyenne = null): float
    {
        if ($satisfactionMoyenne === null) {
            return round($tauxRealisation, 1);
        }

        return round($tauxRealisation * 0.7 + min(100, $satisfactionMoyenne) * 0.3, 1);
    }

    /**
     * @param  array{physique:float,iov:float,recommandations:float,formations:float,conformite:float}  $composantes
     * @param  array<string,float>|null  $poids
     * @return array<string, mixed>
     */
    public static function synthese(array $composantes, ?array $poids = null): array
    {
        $poids ??= self::POIDS_COMPOSANTES;
        $poidsTotal = array_sum($poids);
        $scoreGlobal = 0.0;

        foreach ($poids as $cle => $p) {
            $scoreGlobal += ($composantes[$cle] ?? 0) * ($p / max(0.0001, $poidsTotal));
        }

        return [
            'score_physique' => round($composantes['physique'] ?? 0, 1),
            'score_iov' => round($composantes['iov'] ?? 0, 1),
            'score_recommandations' => round($composantes['recommandations'] ?? 0, 1),
            'score_formations' => round($composantes['formations'] ?? 0, 1),
            'score_conformite' => round($composantes['conformite'] ?? 0, 1),
            'score_global' => round($scoreGlobal, 1),
            'poids' => $poids,
        ];
    }

    public static function niveauPerformance(float $scoreGlobal): string
    {
        return match (true) {
            $scoreGlobal >= 75 => 'satisfaisant',
            $scoreGlobal >= 50 => 'partiel',
            default => 'insuffisant',
        };
    }
}
