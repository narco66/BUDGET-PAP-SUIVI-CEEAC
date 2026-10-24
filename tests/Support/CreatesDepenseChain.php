<?php

namespace Tests\Support;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Tache;
use App\Models\Engagement;
use App\Models\User;
use App\Services\WorkflowService;

/** Helpers PAP + besoin validé pour les tests chaîne de la dépense. */
trait CreatesDepenseChain
{
    /** @return array{0: Activite, 1: LigneBudgetaire} */
    protected function activiteEtLigne(Exercice $exercice, string $codeLigne, float $ouvert): array
    {
        $rubrique = \App\Models\BudgetRubrique::first();
        if (! $rubrique) {
            $titre = \App\Models\BudgetTitre::create(['sens' => 'depense', 'code' => '1', 'intitule' => 'P']);
            $art = \App\Models\BudgetArticle::create(['titre_id' => $titre->id, 'code' => '66', 'intitule' => 'C']);
            $par = \App\Models\BudgetParagraphe::create(['article_id' => $art->id, 'code' => '661', 'intitule' => 'T']);
            $rubrique = \App\Models\BudgetRubrique::create(['paragraphe_id' => $par->id, 'code' => '661000', 'intitule' => 'S']);
        }

        $pilier = Pilier::create(['exercice_id' => $exercice->id, 'code' => 'P-'.uniqid(), 'intitule' => 'Pilier test']);
        $axe = Axe::create(['pilier_id' => $pilier->id, 'code' => 'A1', 'intitule' => 'Axe test']);
        $produit = Produit::create(['axe_id' => $axe->id, 'code' => 'PR1', 'intitule' => 'Produit test']);
        $sp = SousProduit::create(['produit_id' => $produit->id, 'code' => 'SP1', 'intitule' => 'SP test']);
        $activite = Activite::create([
            'sous_produit_id' => $sp->id,
            'produit_id' => $produit->id,
            'code' => 'ACT-'.uniqid(),
            'intitule' => 'Activité test',
            'montant_total' => $ouvert,
        ]);
        $ligne = LigneBudgetaire::create([
            'exercice_id' => $exercice->id,
            'rubrique_id' => $rubrique->id,
            'code' => $codeLigne,
            'montant_ouvert' => $ouvert,
            'activite_id' => $activite->id,
        ]);
        $activite->update(['ligne_budgetaire_id' => $ligne->id]);

        return [$activite, $ligne];
    }

    protected function tachePourActivite(Activite $activite, string $code = 'T1'): Tache
    {
        return Tache::create([
            'activite_id' => $activite->id,
            'code' => $code.'-'.uniqid(),
            'intitule' => 'Tâche test',
            'statut' => 'en_cours',
        ]);
    }

    protected function besoinValide(
        Exercice $exercice,
        Activite $activite,
        LigneBudgetaire $ligne,
        User $user,
        float $montant,
        string $numero = 'EB-TEST',
        ?Tache $tache = null,
        string $priorite = 'moyenne',
    ): ExpressionBesoin {
        return ExpressionBesoin::create([
            'numero' => $numero,
            'exercice_id' => $exercice->id,
            'activite_id' => $activite->id,
            'tache_id' => $tache?->id,
            'ligne_budgetaire_id' => $ligne->id,
            'demandeur_id' => $user->id,
            'objet' => 'Besoin test',
            'montant_estime' => $montant,
            'priorite' => $priorite,
            'statut' => 'valide',
        ]);
    }

    protected function viserEngagement(Engagement $engagement): void
    {
        $wf = app(WorkflowService::class);
        $instance = $engagement->fresh()->workflowActif;
        $wf->valider($instance, $this->user('directeur'));
        $wf->valider($instance->fresh(), $this->user('controleur_financier'));
    }

    protected function viserLiquidation(\App\Models\Liquidation $liquidation): void
    {
        if (blank($liquidation->service_fait)) {
            $liquidation->update(['service_fait' => 'Service fait constaté.']);
        }
        $wf = app(WorkflowService::class);
        if (! $liquidation->fresh()->workflowActif) {
            $wf->demarrer($liquidation, 'LIQUIDATION', $this->user('gestionnaire_budgetaire'));
        }
        $instance = $liquidation->fresh()->workflowActif;
        $wf->valider($instance, $this->user('chef_service'));
        $wf->valider($instance->fresh(), $this->user('controleur_financier'));
    }

    protected function viserOrdonnancement(\App\Models\Ordonnancement $ordonnancement): void
    {
        $wf = app(WorkflowService::class);
        $circuit = app(\App\Services\Depense\SeuilOrdonnancementService::class)->circuit($ordonnancement);
        if (! $ordonnancement->fresh()->workflowActif) {
            $wf->demarrer($ordonnancement, $circuit, $this->user('gestionnaire_budgetaire'));
        }
        $role = $ordonnancement->fresh()->ordonnateur_role ?: 'secretaire_general';
        $wf->valider($ordonnancement->fresh()->workflowActif, $this->user($role));
    }

    protected function viserPaiement(\App\Models\Paiement $paiement): void
    {
        $wf = app(WorkflowService::class);
        if (! $paiement->fresh()->workflowActif) {
            $wf->demarrer($paiement, 'PAIEMENT', $this->user('gestionnaire_budgetaire'));
        }
        $instance = $paiement->fresh()->workflowActif;
        $wf->valider($instance, $this->user('comptable'));
        $wf->valider($instance->fresh(), $this->user('agent_comptable'));
    }
}
