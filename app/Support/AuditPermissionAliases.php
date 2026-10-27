<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Permissions du module Journalisation (§ 8 du référentiel) résolues à partir des permissions existantes,
 * sans création de permission ni modification des rôles :
 * - consultation de ses propres événements et tableau de bord : audit.voir ;
 * - consultation étendue, sécurité, alertes, intégrité, conservation : audit.consulter_journal (auditeur, administrateur) ;
 * - journal de sécurité : aussi admin_technique.security ;
 * - exports et rapports : audit.exporter ; gestion des alertes et contrôle d'intégrité : audit.valider ;
 * - gestion de la conservation : admin_technique.admin.
 * La consultation d'unité (audit.events.view_unit) est accordée par AuditAccesService aux responsables de structure.
 */
final class AuditPermissionAliases
{
    private const ALIAS = [
        'audit.dashboard.view' => ['audit.voir'],
        'audit.events.view_own' => ['audit.voir'],
        'audit.events.view_unit' => ['audit.voir'],
        'audit.events.view_extended' => ['audit.consulter_journal'],
        'audit.security.view' => ['audit.consulter_journal', 'admin_technique.security'],
        'audit.alerts.view' => ['audit.consulter_journal'],
        'audit.alerts.manage' => ['audit.valider'],
        'audit.integrity.view' => ['audit.consulter_journal'],
        'audit.integrity.execute' => ['audit.valider'],
        'audit.reports.generate' => ['audit.exporter'],
        'audit.exports.download' => ['audit.exporter'],
        'audit.retention.view' => ['audit.consulter_journal'],
        'audit.retention.manage' => ['admin_technique.admin'],
    ];

    public static function evaluate(User $user, string $ability): ?bool
    {
        if (! isset(self::ALIAS[$ability])) {
            return null;
        }
        foreach (self::ALIAS[$ability] as $permission) {
            try {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                continue;
            }
        }

        return false;
    }
}
