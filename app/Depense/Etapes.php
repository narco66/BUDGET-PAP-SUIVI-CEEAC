<?php

namespace App\Depense;

use App\Models\Engagement;
use App\Models\Liquidation;
use App\Models\LiquidationPiece;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\ServiceFait;
use App\Services\Depense\DevisePaiementService;
use InvalidArgumentException;

/**
 * Registre des étapes aval de la chaîne de la dépense.
 * Engagement (visé) → Liquidation → Ordonnancement → Paiement.
 * Permet un contrôleur générique unique pilotant les trois étapes.
 */
class Etapes
{
    public static function tout(): array
    {
        return [
            'liquidation' => [
                'model' => Liquidation::class,
                'table' => 'liquidations',
                'label' => 'Liquidation', 'pluriel' => 'Liquidations',
                'prefixe' => 'LIQ', 'icone' => 'bi-receipt-cutoff',
                'parent_type' => 'engagement', 'parent_model' => Engagement::class,
                'parent_cle' => 'engagement_id', 'parent_statut_requis' => 'vise', 'parent_label' => 'Engagement',
                'workflow' => 'LIQUIDATION', 'permission' => 'liquidation',
                'enfant_type' => 'ordonnancement', 'enfant_label' => 'Ordonnancement',
                'date_cle' => 'date_liquidation',
                'pdf_titre' => 'CERTIFICAT DE LIQUIDATION',
                'champs' => [
                    'service_fait_type' => ['type' => 'select', 'label' => 'Type de constat',
                        'options' => ServiceFait::TYPES],
                    'service_fait_reference' => ['type' => 'text', 'label' => 'Référence constat'],
                    'service_fait' => ['type' => 'textarea', 'label' => 'Constatation du service fait'],
                    'piece_type' => ['type' => 'select', 'label' => 'Typologie pièce justificative',
                        'options' => LiquidationPiece::TYPES],
                    'piece_reference' => ['type' => 'text', 'label' => 'Référence pièce'],
                ],
            ],
            'ordonnancement' => [
                'model' => Ordonnancement::class,
                'table' => 'ordonnancements',
                'label' => 'Ordonnancement', 'pluriel' => 'Ordonnancements',
                'prefixe' => 'ORD', 'icone' => 'bi-pen',
                'parent_type' => 'liquidation', 'parent_model' => Liquidation::class,
                'parent_cle' => 'liquidation_id', 'parent_statut_requis' => 'valide', 'parent_label' => 'Liquidation',
                'workflow' => 'ORDONNANCEMENT', 'permission' => 'ordonnancement',
                'enfant_type' => 'paiement', 'enfant_label' => 'Paiement',
                'date_cle' => 'date_ordonnancement',
                'pdf_titre' => "ORDRE D'ORDONNANCEMENT",
                'champs' => [],
            ],
            'paiement' => [
                'model' => Paiement::class,
                'table' => 'paiements',
                'label' => 'Paiement', 'pluriel' => 'Paiements',
                'prefixe' => 'PAY', 'icone' => 'bi-cash-coin',
                'parent_type' => 'ordonnancement', 'parent_model' => Ordonnancement::class,
                'parent_cle' => 'ordonnancement_id', 'parent_statut_requis' => 'valide', 'parent_label' => 'Ordonnancement',
                'workflow' => 'PAIEMENT', 'permission' => 'paiement',
                'enfant_type' => null, 'enfant_label' => null,
                'date_cle' => 'date_paiement',
                'pdf_titre' => 'ORDRE DE PAIEMENT',
                'champs' => [
                    'mode_paiement' => ['type' => 'select', 'label' => 'Mode de paiement', 'requis' => true,
                        'options' => ['virement' => 'Virement', 'cheque' => 'Chèque', 'especes' => 'Espèces']],
                    'devise' => ['type' => 'select', 'label' => 'Devise', 'requis' => true,
                        'options' => DevisePaiementService::DEVISES],
                    'taux_change' => ['type' => 'text', 'label' => 'Taux de change vers XAF'],
                    'reference_bancaire' => ['type' => 'text', 'label' => 'Référence bancaire'],
                ],
            ],
        ];
    }

    public static function get(string $type): array
    {
        $tout = self::tout();
        if (! isset($tout[$type])) {
            throw new InvalidArgumentException("Étape de dépense inconnue : {$type}");
        }

        return $tout[$type] + ['type' => $type];
    }

    public static function existe(string $type): bool
    {
        return isset(self::tout()[$type]);
    }
}
