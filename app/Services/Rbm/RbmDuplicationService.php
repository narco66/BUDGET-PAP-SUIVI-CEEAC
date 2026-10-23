<?php

namespace App\Services\Rbm;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Report / duplication du référentiel RBM d'un exercice vers un autre (phase E8). */
class RbmDuplicationService
{
    /** @return array<string,int> */
    public function dupliquerReferentiel(Exercice $source, Exercice $cible, User $user, bool $forcer = false): array
    {
        if ($source->id === $cible->id) {
            throw new RuntimeException('Les exercices source et cible doivent être distincts.');
        }

        if (Pilier::where('exercice_id', $cible->id)->exists() && ! $forcer) {
            throw new RuntimeException('L\'exercice cible contient déjà des piliers. Videz-le ou utilisez --force.');
        }

        $stats = [
            'piliers' => 0,
            'axes' => 0,
            'produits' => 0,
            'sous_produits' => 0,
            'activites' => 0,
            'taches' => 0,
            'indicateurs' => 0,
        ];

        DB::transaction(function () use ($source, $cible, $user, $forcer, &$stats) {
            if ($forcer) {
                $this->viderReferentielExercice($cible);
            }

            $source->load([
                'piliers.indicateurs',
                'piliers.axes.indicateurs',
                'piliers.axes.produits.indicateurs',
                'piliers.axes.produits.sousProduits.indicateurs',
                'piliers.axes.produits.sousProduits.activites.indicateurs',
                'piliers.axes.produits.sousProduits.activites.taches.indicateurs',
            ]);

            foreach ($source->piliers as $pilier) {
                $newPilier = Pilier::create([
                    'exercice_id' => $cible->id,
                    'pap_id' => null,
                    'code' => $pilier->code,
                    'intitule' => $pilier->intitule,
                    'description' => $pilier->description,
                    'ordre' => $pilier->ordre,
                    'statut' => 'actif',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
                $stats['piliers']++;
                $this->copierIndicateurs($pilier, $newPilier, $user, $cible, $stats);

                foreach ($pilier->axes as $axe) {
                    $newAxe = $newPilier->axes()->create([
                        'code' => $axe->code,
                        'intitule' => $axe->intitule,
                        'objectif_strategique' => $axe->objectif_strategique,
                        'ordre' => $axe->ordre,
                        'statut' => 'actif',
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                    $stats['axes']++;
                    $this->copierIndicateurs($axe, $newAxe, $user, $cible, $stats);

                    foreach ($axe->produits as $produit) {
                        $newProd = $newAxe->produits()->create([
                            'code' => $produit->code,
                            'intitule' => $produit->intitule,
                            'description' => $produit->description,
                            'structure_id' => $produit->structure_id,
                            'ordre' => $produit->ordre,
                            'statut' => 'actif',
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                        $stats['produits']++;
                        $this->copierIndicateurs($produit, $newProd, $user, $cible, $stats);

                        foreach ($produit->sousProduits as $sp) {
                            $newSp = $newProd->sousProduits()->create([
                                'code' => $sp->code,
                                'intitule' => $sp->intitule,
                                'description' => $sp->description,
                                'ordre' => $sp->ordre,
                                'statut' => 'actif',
                                'created_by' => $user->id,
                                'updated_by' => $user->id,
                            ]);
                            $stats['sous_produits']++;
                            $this->copierIndicateurs($sp, $newSp, $user, $cible, $stats);

                            foreach ($sp->activites as $act) {
                                $newAct = $newSp->activites()->create([
                                    'produit_id' => $newProd->id,
                                    'structure_id' => $act->structure_id,
                                    'responsable_user_id' => null,
                                    'ligne_budgetaire_id' => null,
                                    'code' => $act->code,
                                    'intitule' => $act->intitule,
                                    'description' => $act->description,
                                    'montant_total' => $act->montant_total,
                                    'montant_ceeac' => $act->montant_ceeac,
                                    'montant_ptf' => $act->montant_ptf,
                                    'trim_1' => $act->trim_1,
                                    'trim_2' => $act->trim_2,
                                    'trim_3' => $act->trim_3,
                                    'trim_4' => $act->trim_4,
                                    'taux_realisation_physique' => 0,
                                    'taux_realisation_financier' => 0,
                                    'ordre' => $act->ordre,
                                    'statut' => 'actif',
                                    'created_by' => $user->id,
                                    'updated_by' => $user->id,
                                ]);
                                $stats['activites']++;
                                $this->copierIndicateurs($act, $newAct, $user, $cible, $stats);

                                foreach ($act->taches as $tache) {
                                    $newTache = $newAct->taches()->create([
                                        'code' => $tache->code,
                                        'intitule' => $tache->intitule,
                                        'montant' => $tache->montant,
                                        'date_debut' => $tache->date_debut,
                                        'date_fin' => $tache->date_fin,
                                        'statut' => $tache->statut,
                                        'ordre' => $tache->ordre,
                                        'statut_referentiel' => 'actif',
                                        'created_by' => $user->id,
                                        'updated_by' => $user->id,
                                    ]);
                                    $stats['taches']++;
                                    $this->copierIndicateurs($tache, $newTache, $user, $cible, $stats);
                                }
                            }
                        }
                    }
                }
            }
        });

        return $stats;
    }

    /** @param  array<string,int>  $stats */
    private function copierIndicateurs(object $source, object $cible, User $user, Exercice $exerciceCible, array &$stats): void
    {
        if (! method_exists($source, 'indicateurs')) {
            return;
        }

        foreach ($source->indicateurs as $ind) {
            Indicateur::create([
                'rbm_type' => $cible::class,
                'rbm_id' => $cible->id,
                'code' => $this->codeIndicateurDuplique($ind->code, $exerciceCible),
                'libelle' => $ind->libelle,
                'type' => $ind->type,
                'type_indicateur_id' => $ind->type_indicateur_id,
                'unite' => $ind->unite,
                'unite_mesure_id' => $ind->unite_mesure_id,
                'methode_calcul' => $ind->methode_calcul,
                'source_verification' => $ind->source_verification,
                'periodicite' => $ind->periodicite,
                'valeur_reference' => $ind->valeur_reference,
                'valeur_cible' => $ind->valeur_cible,
                'seuil_alerte' => $ind->seuil_alerte,
                'statut' => 'actif',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $stats['indicateurs']++;
        }
    }

    private function codeIndicateurDuplique(string $code, Exercice $exerciceCible): string
    {
        $suffix = '-'.$exerciceCible->annee;
        $candidate = str_ends_with($code, $suffix) ? $code : $code.$suffix;
        $base = $candidate;
        $i = 1;

        while (Indicateur::where('code', $candidate)->exists()) {
            $candidate = substr($base, 0, 35).'-'.$i;
            $i++;
        }

        return substr($candidate, 0, 40);
    }

    private function viderReferentielExercice(Exercice $exercice): void
    {
        $exercice->load('piliers.axes.produits.sousProduits.activites.taches');

        foreach ($exercice->piliers as $pilier) {
            foreach ($pilier->axes as $axe) {
                foreach ($axe->produits as $prod) {
                    foreach ($prod->sousProduits as $sp) {
                        foreach ($sp->activites as $act) {
                            Indicateur::where('rbm_type', Tache::class)->whereIn('rbm_id', $act->taches->pluck('id'))->delete();
                            Indicateur::where('rbm_type', Activite::class)->where('rbm_id', $act->id)->delete();
                            $act->taches()->delete();
                            $act->delete();
                        }
                        Indicateur::where('rbm_type', SousProduit::class)->where('rbm_id', $sp->id)->delete();
                        $sp->delete();
                    }
                    Indicateur::where('rbm_type', Produit::class)->where('rbm_id', $prod->id)->delete();
                    $prod->delete();
                }
                Indicateur::where('rbm_type', Axe::class)->where('rbm_id', $axe->id)->delete();
                $axe->delete();
            }
            Indicateur::where('rbm_type', Pilier::class)->where('rbm_id', $pilier->id)->delete();
            $pilier->delete();
        }
    }
}
