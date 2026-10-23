<?php

namespace App\Services\Demo;

use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Génère un dossier de dépense de démonstration de bout en bout, en respectant
 * STRICTEMENT les invariants : Σ imputations = montant ; payé ≤ ordonnancé ≤ liquidé ≤ engagé ≤ disponible.
 * Idempotent : un dossier déjà présent (par numéro d'engagement) n'est pas recréé.
 */
class DemoChaineBuilder
{
    public function __construct(private readonly Exercice $exercice)
    {
    }

    /**
     * @param array $spec {
     *   numero:string, objet:string, type:string, structure_id:?int, demandeur_id:?int,
     *   controleur_id:?int, comptable_id:?int, besoin_statut:string, engagement_statut:string,
     *   imputations: array<int,array{ligne:string, montant:int}>,
     *   liquide:?int, ordonnance:?int, paye:?int, paye_statut:?string, mode_paiement:?string,
     *   pieces: array<int,array{nom:string, categorie:string, cible:string}>
     * }
     */
    public function construire(array $spec): ?Engagement
    {
        $besoinNumero = 'EB-'.substr($spec['numero'], 4);

        // Garde d'idempotence sur le besoin (couvre aussi les dossiers rejetés sans engagement)
        if (ExpressionBesoin::where('numero', $besoinNumero)->exists()) {
            return Engagement::firstWhere('numero', $spec['numero']);
        }

        return DB::transaction(function () use ($spec, $besoinNumero) {
            $total = collect($spec['imputations'])->sum('montant');

            // 1) Expression du besoin
            $premiereLigne = $this->ligne($spec['imputations'][0]['ligne']);
            $besoin = ExpressionBesoin::create([
                'numero' => $besoinNumero,
                'exercice_id' => $this->exercice->id,
                'structure_id' => $spec['structure_id'] ?? null,
                'activite_id' => $premiereLigne->activite_id,
                'ligne_budgetaire_id' => $premiereLigne->id,
                'demandeur_id' => $spec['demandeur_id'] ?? null,
                'objet' => $spec['objet'],
                'justification' => 'Dossier de démonstration — '.$spec['objet'],
                'montant_estime' => $total,
                'date_besoin' => $this->exercice->date_debut ?? now(),
                'statut' => $spec['besoin_statut'] ?? 'valide',
            ]);
            $this->attacherPieces($besoin, $spec, 'besoin');

            // Besoin non converti : pas d'engagement
            if (in_array($spec['besoin_statut'] ?? 'valide', ['brouillon', 'soumis', 'rejete'], true)) {
                return null;
            }

            // 2) Engagement + imputations
            $statutEng = $spec['engagement_statut'] ?? 'vise';
            $reserve = in_array($statutEng, ['soumis', 'vise'], true);

            $engagement = Engagement::create([
                'numero' => $spec['numero'],
                'exercice_id' => $this->exercice->id,
                'expression_besoin_id' => $besoin->id,
                'activite_id' => $besoin->activite_id,
                'type' => $spec['type'] ?? 'simple',
                'objet' => $spec['objet'],
                'beneficiaire' => $spec['beneficiaire'] ?? 'Fournisseur démonstration',
                'montant_total' => $total,
                'date_engagement' => $this->exercice->date_debut ?? now(),
                'statut' => $statutEng,
                'cree_par' => $spec['demandeur_id'] ?? null,
                'controleur_id' => $statutEng === 'vise' ? ($spec['controleur_id'] ?? null) : null,
                'vise_le' => $statutEng === 'vise' ? now() : null,
                'credits_reserves' => $reserve,
            ]);

            $imputations = [];
            foreach ($spec['imputations'] as $imp) {
                $ligne = $this->ligne($imp['ligne']);
                if ($reserve && ! $ligne->peutEngager((float) $imp['montant'])) {
                    throw new RuntimeException("Crédits insuffisants sur {$ligne->code} pour le dossier {$spec['numero']}.");
                }
                $impModel = $engagement->imputations()->create([
                    'ligne_budgetaire_id' => $ligne->id, 'montant' => $imp['montant'],
                ]);
                if ($reserve) {
                    $ligne->increment('montant_engage', (float) $imp['montant']);
                }
                $imputations[] = [$impModel, $ligne, (float) $imp['montant']];
            }
            $this->attacherPieces($engagement, $spec, 'engagement');

            if ($statutEng !== 'vise') {
                return $engagement; // brouillon / annulé : chaîne aval stoppée
            }

            // 3) Liquidation
            $liquide = (int) ($spec['liquide'] ?? 0);
            if ($liquide <= 0) {
                return $engagement;
            }
            $liq = Liquidation::create([
                'numero' => 'LIQ-'.substr($spec['numero'], 4), 'engagement_id' => $engagement->id,
                'montant' => $liquide, 'date_liquidation' => now(), 'service_fait' => 'Service fait constaté.',
                'statut' => 'valide', 'cree_par' => $spec['demandeur_id'] ?? null,
                'valide_par' => $spec['controleur_id'] ?? null, 'valide_le' => now(), 'compteurs_appliques' => true,
            ]);
            $this->repartir($liquide, $total, $imputations, 'montant_liquide');
            $this->attacherPieces($liq, $spec, 'liquidation');

            // 4) Ordonnancement
            $ordonnance = (int) ($spec['ordonnance'] ?? 0);
            if ($ordonnance <= 0) {
                return $engagement;
            }
            $ord = Ordonnancement::create([
                'numero' => 'ORD-'.substr($spec['numero'], 4), 'liquidation_id' => $liq->id,
                'montant' => $ordonnance, 'date_ordonnancement' => now(), 'statut' => 'valide',
                'cree_par' => $spec['demandeur_id'] ?? null, 'valide_par' => $spec['controleur_id'] ?? null,
                'valide_le' => now(), 'compteurs_appliques' => true,
            ]);
            $this->repartir($ordonnance, $total, $imputations, 'montant_ordonnance');

            // 5) Paiement
            $paye = (int) ($spec['paye'] ?? 0);
            if ($paye <= 0) {
                return $engagement;
            }
            $payeStatut = $spec['paye_statut'] ?? 'valide';
            $pai = Paiement::create([
                'numero' => 'PAY-'.substr($spec['numero'], 4), 'ordonnancement_id' => $ord->id,
                'montant' => $paye, 'date_paiement' => now(), 'mode_paiement' => $spec['mode_paiement'] ?? 'virement',
                'reference_bancaire' => 'VIR-2026-'.substr($spec['numero'], -4),
                'statut' => $payeStatut, 'cree_par' => $spec['demandeur_id'] ?? null,
                'valide_par' => $payeStatut === 'valide' ? ($spec['comptable_id'] ?? null) : null,
                'valide_le' => $payeStatut === 'valide' ? now() : null,
                'compteurs_appliques' => $payeStatut === 'valide',
            ]);
            if ($payeStatut === 'valide') {
                $this->repartir($paye, $total, $imputations, 'montant_paye');
            }
            $this->attacherPieces($pai, $spec, 'paiement');

            return $engagement;
        });
    }

    private function ligne(string $code): LigneBudgetaire
    {
        $ligne = LigneBudgetaire::where('exercice_id', $this->exercice->id)->where('code', $code)->first();
        if (! $ligne) {
            throw new RuntimeException("Ligne budgétaire {$code} introuvable pour l'exercice {$this->exercice->annee}.");
        }

        return $ligne;
    }

    /** Répartit $totalStage sur les lignes au prorata des imputations (reste sur la dernière) et incrémente le compteur. */
    private function repartir(int $totalStage, int $engagementTotal, array $imputations, string $colonne): void
    {
        $cumul = 0;
        $dernier = count($imputations) - 1;
        foreach ($imputations as $i => [, $ligne, $montantImp]) {
            $part = $i === $dernier
                ? $totalStage - $cumul
                : (int) round($totalStage * $montantImp / $engagementTotal);
            $cumul += $part;
            $ligne->increment($colonne, $part);
        }
    }

    private function attacherPieces(object $objet, array $spec, string $cible): void
    {
        foreach ($spec['pieces'] ?? [] as $piece) {
            if (($piece['cible'] ?? 'engagement') !== $cible) {
                continue;
            }
            $objet->pieces()->create([
                'nom' => $piece['nom'],
                'chemin' => 'demo/'.\Illuminate\Support\Str::slug($piece['nom']).'.pdf', // métadonnée (pas de fichier physique)
                'type_mime' => 'application/pdf',
                'taille' => random_int(40, 600) * 1024,
                'categorie' => $piece['categorie'] ?? 'justificatif',
                'televerse_par' => $spec['demandeur_id'] ?? null,
            ]);
        }
    }
}
