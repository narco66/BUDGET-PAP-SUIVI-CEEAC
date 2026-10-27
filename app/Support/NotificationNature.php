<?php

namespace App\Support;

/**
 * Nature d'une notification (référentiel § 3) et état de l'action attendue (§ 10).
 * La lecture et l'action sont indépendantes : une notification lue peut rester « à traiter ».
 */
final class NotificationNature
{
    public const ACTION = 'action';

    public const INFORMATION = 'information';

    public const ALERTE = 'alerte';

    public const RAPPEL = 'rappel';

    public const SYSTEME = 'systeme';

    public const LIBELLES = [
        self::ACTION => 'Action requise',
        self::INFORMATION => 'Information',
        self::ALERTE => 'Alerte',
        self::RAPPEL => 'Rappel',
        self::SYSTEME => 'Système',
    ];

    public const ACTION_EN_ATTENTE = 'en_attente';

    public const ACTION_TRAITEE = 'traitee';

    public const ACTION_ANNULEE = 'annulee';

    public const ACTION_EXPIREE = 'expiree';

    public const ETATS_ACTION = [
        self::ACTION_EN_ATTENTE => 'À traiter',
        self::ACTION_TRAITEE => 'Traitée',
        self::ACTION_ANNULEE => 'Annulée',
        self::ACTION_EXPIREE => 'Expirée',
    ];

    /** Nature par défaut déduite du type (les types restent paramétrables). */
    public static function duType(string $typeCode): string
    {
        return match (true) {
            in_array($typeCode, ['workflow.etape_en_attente', 'workflow.reaffectation'], true) => self::ACTION,
            str_contains($typeCode, 'relance') || str_contains($typeCode, 'rappel') => self::RAPPEL,
            str_starts_with($typeCode, 'alerte.') || str_contains($typeCode, 'escalade')
                || in_array($typeCode, ['ged.acces_critique', 'ged.expiration', 'depense.engagement_non_soumis'], true) => self::ALERTE,
            str_starts_with($typeCode, 'systeme.') => self::SYSTEME,
            default => self::INFORMATION,
        };
    }

    /** Une nature appelant une intervention porte un état d'action. */
    public static function porteUneAction(string $nature): bool
    {
        return in_array($nature, [self::ACTION, self::RAPPEL], true);
    }
}
