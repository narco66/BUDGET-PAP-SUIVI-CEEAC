<?php

namespace App\Rbm;

use App\Models\Activite;
use App\Models\Axe;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Tache;
use InvalidArgumentException;

/**
 * Registre de la chaîne RBM (D2) : Pilier → Axe → Produit → Sous-Produit → Activité → Tâche.
 * Décrit, par niveau, le modèle, le parent, les enfants et les champs éditables (avec type).
 * Permet un CRUD générique unique au lieu de six contrôleurs dupliqués.
 */
class Niveaux
{
    /** @return array<string,array> */
    public static function tout(): array
    {
        return [
            'pilier' => [
                'model' => Pilier::class,
                'table' => 'piliers',
                'label' => 'Pilier',
                'icone' => 'bi-bullseye',
                'parent_niveau' => null,           // rattaché à l'Exercice
                'parent_cle' => 'exercice_id',
                'enfant_niveau' => 'axe',
                'relation_enfants' => 'axes',
                'champs' => [
                    'code' => ['type' => 'text', 'requis' => true, 'max' => 40, 'code_unique' => true],
                    'intitule' => ['type' => 'text', 'requis' => true, 'max' => 1000],
                    'description' => ['type' => 'textarea'],
                    'ordre' => ['type' => 'number', 'defaut' => 0],
                ],
            ],
            'axe' => [
                'model' => Axe::class,
                'table' => 'axes',
                'label' => 'Axe',
                'icone' => 'bi-signpost-split',
                'parent_niveau' => 'pilier',
                'parent_cle' => 'pilier_id',
                'enfant_niveau' => 'produit',
                'relation_enfants' => 'produits',
                'champs' => [
                    'code' => ['type' => 'text', 'requis' => true, 'max' => 40, 'code_unique' => true],
                    'intitule' => ['type' => 'text', 'requis' => true, 'max' => 1000],
                    'objectif_strategique' => ['type' => 'textarea'],
                    'ordre' => ['type' => 'number', 'defaut' => 0],
                ],
            ],
            'produit' => [
                'model' => Produit::class,
                'table' => 'produits',
                'label' => 'Produit',
                'icone' => 'bi-box-seam',
                'parent_niveau' => 'axe',
                'parent_cle' => 'axe_id',
                'enfant_niveau' => 'sous_produit',
                'relation_enfants' => 'sousProduits',
                'champs' => [
                    'code' => ['type' => 'text', 'requis' => true, 'max' => 40, 'code_unique' => true],
                    'intitule' => ['type' => 'text', 'requis' => true, 'max' => 1000],
                    'structure_id' => ['type' => 'select', 'source' => 'structures', 'label' => 'Structure responsable'],
                    'description' => ['type' => 'textarea'],
                    'ordre' => ['type' => 'number', 'defaut' => 0],
                ],
            ],
            'sous_produit' => [
                'model' => SousProduit::class,
                'table' => 'sous_produits',
                'label' => 'Sous-Produit',
                'icone' => 'bi-boxes',
                'parent_niveau' => 'produit',
                'parent_cle' => 'produit_id',
                'enfant_niveau' => 'activite',
                'relation_enfants' => 'activites',
                'champs' => [
                    'code' => ['type' => 'text', 'requis' => true, 'max' => 40, 'code_unique' => true],
                    'intitule' => ['type' => 'text', 'requis' => true, 'max' => 1000],
                    'description' => ['type' => 'textarea'],
                    'ordre' => ['type' => 'number', 'defaut' => 0],
                ],
            ],
            'activite' => [
                'model' => Activite::class,
                'table' => 'activites',
                'label' => 'Activité',
                'icone' => 'bi-list-task',
                'parent_niveau' => 'sous_produit',
                'parent_cle' => 'sous_produit_id',
                'enfant_niveau' => 'tache',
                'relation_enfants' => 'taches',
                'champs' => [
                    'code' => ['type' => 'text', 'requis' => true, 'max' => 40, 'code_unique' => true],
                    'intitule' => ['type' => 'text', 'requis' => true, 'max' => 1000],
                    'structure_id' => ['type' => 'select', 'source' => 'structures', 'label' => 'Structure'],
                    'responsable_user_id' => ['type' => 'select', 'source' => 'users', 'label' => 'Responsable'],
                    'ligne_budgetaire_id' => ['type' => 'select', 'source' => 'lignes', 'label' => 'Ligne budgétaire'],
                    'montant_ceeac' => ['type' => 'decimal', 'label' => 'Montant CEEAC', 'defaut' => 0],
                    'montant_ptf' => ['type' => 'decimal', 'label' => 'Montant PTF', 'defaut' => 0],
                    'trimestres' => ['type' => 'trimestres', 'label' => 'Chronogramme'],
                    'ordre' => ['type' => 'number', 'defaut' => 0],
                ],
            ],
            'tache' => [
                'model' => Tache::class,
                'table' => 'taches',
                'label' => 'Tâche',
                'icone' => 'bi-check2-square',
                'parent_niveau' => 'activite',
                'parent_cle' => 'activite_id',
                'enfant_niveau' => null,
                'relation_enfants' => null,
                'champs' => [
                    'code' => ['type' => 'text', 'requis' => true, 'max' => 40, 'code_unique' => true],
                    'intitule' => ['type' => 'text', 'requis' => true, 'max' => 1000],
                    'responsable_user_id' => ['type' => 'select', 'source' => 'users', 'label' => 'Responsable'],
                    'date_debut' => ['type' => 'date'],
                    'date_fin' => ['type' => 'date'],
                    'montant' => ['type' => 'decimal', 'defaut' => 0],
                    'statut' => ['type' => 'select', 'source' => 'statuts_tache', 'label' => 'Statut'],
                    'ordre' => ['type' => 'number', 'defaut' => 0],
                ],
            ],
        ];
    }

    public static function get(string $niveau): array
    {
        $tout = self::tout();
        if (! isset($tout[$niveau])) {
            throw new InvalidArgumentException("Niveau RBM inconnu : {$niveau}");
        }

        return $tout[$niveau] + ['niveau' => $niveau];
    }

    public static function existe(string $niveau): bool
    {
        return isset(self::tout()[$niveau]);
    }
}
