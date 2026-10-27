<?php

namespace App\Services\Depense;

use App\Models\AuditDepense;
use App\Models\EcritureComptable;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Budget\CompteurMouvementAuditService;
use App\Support\Numero;
use Illuminate\Support\Facades\DB;

/** Audit complet de la chaîne de la dépense (phases D3–D5). */
class DepenseAuditService
{
    /** @var array<string,int> */
    private const POIDS_GRAVITE = ['elevee' => 10, 'moyenne' => 5, 'faible' => 1];

    public function __construct(private readonly CompteurMouvementAuditService $compteurs)
    {
    }

    /** @return array<string,mixed> */
    public function executer(?int $exerciceId, ?User $auditeur = null, bool $persister = false): array
    {
        $sections = [
            'amont' => $this->anomaliesAmont($exerciceId),
            'enchainement' => $this->anomaliesEnchainement($exerciceId),
            'montants' => $this->anomaliesMontants($exerciceId),
            'paiements' => $this->anomaliesPaiements($exerciceId),
            'comptabilite' => $this->anomaliesComptabilite($exerciceId),
            'compteurs' => $this->anomaliesCompteurs($exerciceId),
        ];

        $anomalies = array_merge(...array_values($sections));
        $synthese = $this->synthese($exerciceId);
        $score = $this->calculerScore($anomalies);

        $rapport = [
            'exercice_id' => $exerciceId,
            'genere_le' => now()->toIso8601String(),
            'synthese' => $synthese,
            'sections' => collect($sections)->map(fn ($items) => count($items))->all(),
            'par_gravite' => collect($anomalies)->countBy('gravite')->all(),
            'score_conformite' => $score,
            'anomalies' => $anomalies,
            'anomalies_total' => count($anomalies),
        ];

        if ($persister && $exerciceId) {
            $this->persister($exerciceId, $rapport, $auditeur);
        }

        return $rapport;
    }

    /** @param list<array<string,mixed>> $anomalies */
    public function depasseSeuil(array $anomalies, string $seuil = 'moyenne'): bool
    {
        $ordre = ['faible' => 1, 'moyenne' => 2, 'elevee' => 3];
        $min = $ordre[$seuil] ?? 2;

        foreach ($anomalies as $a) {
            $g = $a['gravite'] ?? 'moyenne';
            if (($ordre[$g] ?? 2) >= $min) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesAmont(?int $exerciceId): array
    {
        $anomalies = [];

        $sansBesoin = Engagement::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNull('expression_besoin_id')
            ->whereNotIn('statut', ['annule', 'rejete'])
            ->get(['id', 'numero']);

        foreach ($sansBesoin as $e) {
            $anomalies[] = ['type' => 'engagement_sans_besoin', 'numero' => $e->numero, 'gravite' => 'elevee', 'section' => 'amont'];
        }

        $sansActivite = Engagement::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNull('activite_id')
            ->whereNotIn('statut', ['annule', 'rejete'])
            ->get(['id', 'numero']);

        foreach ($sansActivite as $e) {
            $anomalies[] = ['type' => 'engagement_sans_activite', 'numero' => $e->numero, 'gravite' => 'moyenne', 'section' => 'amont'];
        }

        $besoinsSansLigne = ExpressionBesoin::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNull('ligne_budgetaire_id')
            ->whereIn('statut', ['valide', 'converti', 'soumis'])
            ->pluck('numero');

        foreach ($besoinsSansLigne as $numero) {
            $anomalies[] = ['type' => 'besoin_sans_ligne_budgetaire', 'numero' => $numero, 'gravite' => 'elevee', 'section' => 'amont'];
        }

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesEnchainement(?int $exerciceId): array
    {
        $anomalies = [];

        $liqInvalides = DB::table('liquidations as l')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->when($exerciceId, fn ($q) => $q->where('e.exercice_id', $exerciceId))
            ->whereNotIn('e.statut', ['vise'])
            ->whereNotIn('l.statut', ['rejete'])
            ->whereNull('l.deleted_at')
            ->select('l.numero', 'e.numero as engagement')
            ->get();

        foreach ($liqInvalides as $r) {
            $anomalies[] = [
                'type' => 'liquidation_engagement_non_vise',
                'numero' => $r->numero,
                'engagement' => $r->engagement,
                'gravite' => 'elevee',
                'section' => 'enchainement',
            ];
        }

        $ordInvalides = DB::table('ordonnancements as o')
            ->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->when($exerciceId, fn ($q) => $q->where('e.exercice_id', $exerciceId))
            ->where('l.statut', '!=', 'valide')
            ->whereNotIn('o.statut', ['rejete'])
            ->whereNull('o.deleted_at')
            ->pluck('o.numero');

        foreach ($ordInvalides as $numero) {
            $anomalies[] = ['type' => 'ordonnancement_liquidation_non_validee', 'numero' => $numero, 'gravite' => 'elevee', 'section' => 'enchainement'];
        }

        $payInvalides = DB::table('paiements as p')
            ->join('ordonnancements as o', 'o.id', '=', 'p.ordonnancement_id')
            ->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->when($exerciceId, fn ($q) => $q->where('e.exercice_id', $exerciceId))
            ->where('o.statut', '!=', 'valide')
            ->whereNotIn('p.statut', ['rejete'])
            ->whereNull('p.deleted_at')
            ->pluck('p.numero');

        foreach ($payInvalides as $numero) {
            $anomalies[] = ['type' => 'paiement_ordonnancement_non_valide', 'numero' => $numero, 'gravite' => 'elevee', 'section' => 'enchainement'];
        }

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesMontants(?int $exerciceId): array
    {
        $anomalies = [];

        $engagements = Engagement::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereIn('statut', ['vise', 'soumis'])
            ->withSum(['liquidations as total_liquide' => fn ($q) => $q->whereNotIn('statut', ['rejete'])], 'montant')
            ->get(['id', 'numero', 'montant_total']);

        foreach ($engagements as $e) {
            $liquide = (float) ($e->total_liquide ?? 0);
            if ($liquide > (float) $e->montant_total + 0.05) {
                $anomalies[] = [
                    'type' => 'liquidations_depassent_engagement',
                    'numero' => $e->numero,
                    'engage' => (float) $e->montant_total,
                    'liquide' => $liquide,
                    'gravite' => 'elevee',
                    'section' => 'montants',
                ];
            }
        }

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesPaiements(?int $exerciceId): array
    {
        $anomalies = [];

        $sansOrdre = Paiement::where('statut', 'valide')
            ->whereNull('ordre_paiement_id')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $exerciceId ? $q->where('exercice_id', $exerciceId) : $q)
            ->pluck('numero');

        foreach ($sansOrdre as $numero) {
            $anomalies[] = ['type' => 'paiement_sans_ordre', 'numero' => $numero, 'gravite' => 'moyenne', 'section' => 'paiements'];
        }

        $sansRapprochement = Paiement::where('statut', 'valide')
            ->whereNull('rapprochement_bancaire_id')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $exerciceId ? $q->where('exercice_id', $exerciceId) : $q)
            ->pluck('numero');

        foreach ($sansRapprochement as $numero) {
            $anomalies[] = ['type' => 'paiement_non_rapproche', 'numero' => $numero, 'gravite' => 'faible', 'section' => 'paiements'];
        }

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesComptabilite(?int $exerciceId): array
    {
        $anomalies = [];

        $sansEcriture = Paiement::where('statut', 'valide')
            ->whereDoesntHave('ecrituresComptables')
            ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $exerciceId ? $q->where('exercice_id', $exerciceId) : $q)
            ->pluck('numero');

        foreach ($sansEcriture as $numero) {
            $anomalies[] = ['type' => 'paiement_sans_ecriture_comptable', 'numero' => $numero, 'gravite' => 'moyenne', 'section' => 'comptabilite'];
        }

        $ecrituresOrphelines = EcritureComptable::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('paiement_id')
            ->whereDoesntHave('paiement')
            ->count();

        if ($ecrituresOrphelines > 0) {
            $anomalies[] = [
                'type' => 'ecritures_orphelines',
                'numero' => "{$ecrituresOrphelines} écriture(s)",
                'gravite' => 'elevee',
                'section' => 'comptabilite',
            ];
        }

        return $anomalies;
    }

    /** @return list<array<string,mixed>> */
    private function anomaliesCompteurs(?int $exerciceId): array
    {
        $rapport = $this->compteurs->analyser($exerciceId);
        $anomalies = [];

        foreach ($rapport['ecarts_compteurs'] ?? [] as $ecart) {
            $anomalies[] = [
                'type' => 'ecart_compteur_'.$ecart['type'],
                'numero' => $ecart['code'] ?? $ecart['ligne_id'] ?? '?',
                'gravite' => 'elevee',
                'section' => 'compteurs',
                'detail' => $ecart,
            ];
        }

        foreach ($rapport['anomalies_semantiques'] ?? [] as $sem) {
            $anomalies[] = [
                'type' => strtolower($sem['type'] ?? 'anomalie_semantique'),
                'numero' => $sem['reference'] ?? '?',
                'gravite' => 'moyenne',
                'section' => 'compteurs',
                'detail' => $sem,
            ];
        }

        return $anomalies;
    }

    /** @param list<array<string,mixed>> $anomalies */
    private function calculerScore(array $anomalies): int
    {
        $penalite = 0;
        foreach ($anomalies as $a) {
            $penalite += self::POIDS_GRAVITE[$a['gravite'] ?? 'moyenne'] ?? 5;
        }

        return max(0, 100 - min(100, $penalite));
    }

    /** @return array<string,int|float> */
    private function synthese(?int $exerciceId): array
    {
        $engQuery = Engagement::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId));

        return [
            'besoins' => ExpressionBesoin::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))->count(),
            'besoins_valides' => ExpressionBesoin::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))->whereIn('statut', ['valide', 'converti'])->count(),
            'engagements' => (clone $engQuery)->count(),
            'engagements_vises' => (clone $engQuery)->where('statut', 'vise')->count(),
            'liquidations_validees' => Liquidation::whereHas('engagement', fn ($q) => $exerciceId ? $q->where('exercice_id', $exerciceId) : $q)
                ->where('statut', 'valide')->count(),
            'ordonnancements_valides' => Ordonnancement::whereHas('liquidation.engagement', fn ($q) => $exerciceId ? $q->where('exercice_id', $exerciceId) : $q)
                ->where('statut', 'valide')->count(),
            'paiements_valides' => Paiement::whereHas('ordonnancement.liquidation.engagement', fn ($q) => $exerciceId ? $q->where('exercice_id', $exerciceId) : $q)
                ->where('statut', 'valide')->count(),
            'ecritures_comptables' => EcritureComptable::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))->count(),
            'montant_engage' => (float) (clone $engQuery)->where('statut', 'vise')->sum('montant_total'),
            'montant_paye' => (float) Paiement::regles()
                ->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $exerciceId ? $q->where('exercice_id', $exerciceId) : $q)
                ->sum(DB::raw('COALESCE(montant_xaf, montant)')),
        ];
    }

    /** @param array<string,mixed> $rapport */
    private function persister(int $exerciceId, array $rapport, ?User $auditeur): void
    {
        $exercice = Exercice::find($exerciceId);
        $annee = $exercice?->annee ?? (int) date('Y');

        AuditDepense::create([
            'exercice_id' => $exerciceId,
            'reference' => Numero::suivant('audit_depenses', 'AUD-D', $annee, 'reference'),
            'score_conformite' => (int) ($rapport['score_conformite'] ?? 0),
            'snapshot' => $rapport['synthese'] ?? [],
            'anomalies' => $rapport['anomalies'] ?? [],
            'audite_par' => $auditeur?->id,
            'audite_le' => now(),
        ]);
    }
}
