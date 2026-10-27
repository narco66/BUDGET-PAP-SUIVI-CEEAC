<?php

namespace App\Services\Depense;

use App\Models\EcritureComptable;
use App\Models\Encaissement;
use App\Models\Paiement;
use App\Support\Numero;
use Illuminate\Support\Collection;

/** Génération d'écritures SYSCOHADA à partir des paiements validés (phase D4). */
class EcritureComptableService
{
    public function __construct(private readonly DevisePaiementService $devises)
    {
    }

    /** @return Collection<int, EcritureComptable> */
    public function genererPourPaiement(Paiement $paiement): Collection
    {
        if ($paiement->statut !== 'valide') {
            return collect();
        }

        if (EcritureComptable::where('paiement_id', $paiement->id)->exists()) {
            return EcritureComptable::where('paiement_id', $paiement->id)->get();
        }

        $paiement->loadMissing('details.ligneBudgetaire.rubrique', 'ordonnancement.liquidation.engagement');
        $engagement = $paiement->engagementRacine();
        $exerciceId = $engagement->exercice_id;
        $annee = $engagement->exercice?->annee ?? (int) date('Y');
        $montantXaf = $this->devises->montantXaf($paiement);
        $date = $paiement->date_paiement ?? now()->toDateString();
        $ecritures = collect();

        $details = $paiement->details;
        if ($details->isEmpty()) {
            app(PaiementDetailService::class)->genererDepuisEngagement($paiement);
            $details = $paiement->fresh()->details()->with('ligneBudgetaire.rubrique')->get();
        }

        foreach ($details as $detail) {
            $compteCharge = $this->compteCharge($detail->ligneBudgetaire?->rubrique?->code);
            $partXaf = $montantXaf > 0 && (float) $paiement->montant > 0
                ? round((float) $detail->montant * ($montantXaf / (float) $paiement->montant), 2)
                : (float) $detail->montant;

            $ecritures->push(EcritureComptable::create([
                'exercice_id' => $exerciceId,
                'paiement_id' => $paiement->id,
                'engagement_id' => $engagement->id,
                'journal' => 'OD',
                'numero_piece' => Numero::suivant('ecritures_comptables', 'EC', $annee, 'numero_piece'),
                'date_ecriture' => $date,
                'compte_debit' => $compteCharge,
                'compte_credit' => EcritureComptable::COMPTES['banque'],
                'montant' => $partXaf,
                'devise' => 'XAF',
                'libelle' => "Paiement {$paiement->numero} — {$engagement->objet}",
                'reference' => $paiement->numero,
                'statut' => 'valide',
                'metadata' => [
                    'ligne_budgetaire' => $detail->ligneBudgetaire?->code,
                    'devise_origine' => $paiement->devise,
                    'montant_origine' => (float) $detail->montant,
                ],
            ]));
        }

        if ($ecritures->isEmpty()) {
            $ecritures->push(EcritureComptable::create([
                'exercice_id' => $exerciceId,
                'paiement_id' => $paiement->id,
                'engagement_id' => $engagement->id,
                'journal' => 'OD',
                'numero_piece' => Numero::suivant('ecritures_comptables', 'EC', $annee, 'numero_piece'),
                'date_ecriture' => $date,
                'compte_debit' => EcritureComptable::COMPTES['charges_prefixe'].'61000',
                'compte_credit' => EcritureComptable::COMPTES['banque'],
                'montant' => $montantXaf,
                'devise' => 'XAF',
                'libelle' => "Paiement {$paiement->numero}",
                'reference' => $paiement->numero,
                'statut' => 'valide',
            ]));
        }

        return $ecritures;
    }

    /** @return Collection<int, EcritureComptable> */
    public function genererPourEncaissement(Encaissement $encaissement): Collection
    {
        if (! $encaissement->estComptabilise()) {
            return collect();
        }

        if (EcritureComptable::where('encaissement_id', $encaissement->id)->exists()) {
            return EcritureComptable::where('encaissement_id', $encaissement->id)->get();
        }

        $encaissement->loadMissing('recette.rubrique', 'recette.exercice');
        $recette = $encaissement->recette;
        if (! $recette) {
            return collect();
        }

        $exerciceId = $recette->exercice_id;
        $annee = $recette->exercice?->annee ?? (int) date('Y');
        $montantXaf = (float) $encaissement->montant_xaf > 0
            ? (float) $encaissement->montant_xaf
            : (float) $encaissement->montant;
        $date = $encaissement->date_encaissement ?? now()->toDateString();
        $compteRecette = $this->compteRecette($recette->rubrique?->code);

        return collect([
            EcritureComptable::create([
                'exercice_id' => $exerciceId,
                'encaissement_id' => $encaissement->id,
                'journal' => 'BQ',
                'numero_piece' => Numero::suivant('ecritures_comptables', 'EC', $annee, 'numero_piece'),
                'date_ecriture' => $date,
                'compte_debit' => EcritureComptable::COMPTES['banque'],
                'compte_credit' => $compteRecette,
                'montant' => $montantXaf,
                'devise' => 'XAF',
                'libelle' => "Encaissement {$encaissement->reference} — {$recette->reference}",
                'reference' => $encaissement->reference,
                'statut' => 'valide',
                'metadata' => [
                    'rubrique' => $recette->rubrique?->code,
                    'devise_origine' => $encaissement->devise,
                    'montant_origine' => (float) $encaissement->montant,
                ],
            ]),
        ]);
    }

    public function marquerExportees(int $exerciceId): int
    {
        return EcritureComptable::where('exercice_id', $exerciceId)
            ->where('statut', 'valide')
            ->update(['statut' => 'exportee', 'exportee_le' => now()]);
    }

    private function compteCharge(?string $codeRubrique): string
    {
        if (! $codeRubrique) {
            return EcritureComptable::COMPTES['charges_prefixe'].'61000';
        }

        $code = preg_replace('/\D/', '', $codeRubrique) ?: '61000';

        return str_starts_with($code, '6') ? $code : EcritureComptable::COMPTES['charges_prefixe'].$code;
    }

    private function compteRecette(?string $codeRubrique): string
    {
        if (! $codeRubrique) {
            return '721010';
        }

        $code = preg_replace('/\D/', '', $codeRubrique) ?: '721010';

        return str_starts_with($code, '7') ? $code : EcritureComptable::COMPTES['recettes_prefixe'].$code;
    }
}
