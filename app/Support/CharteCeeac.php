<?php

namespace App\Support;

/** Charte graphique CEEAC — couleurs et libellés institutionnels (P3-8). */
final class CharteCeeac
{
    /** @return array<string, mixed> Contexte complet pour les vues d'export. */
    public static function export(): array
    {
        return [
            'institution' => self::institution(),
            'sous_titre' => self::sousTitre(),
            'systeme' => self::systeme(),
            'couleur_primaire' => self::primaire(),
            'couleur_fonce' => self::fonce(),
            'couleur_accent' => self::accent(),
            'couleur_fond_tableau' => self::fondTableau(),
            'couleur_texte_secondaire' => self::texteSecondaire(),
            'police_pdf' => self::policePdf(),
            'mention_pied' => self::mentionPied(),
            'confidentialite' => self::confidentialite(),
        ];
    }

    public static function institution(): string
    {
        return (string) config('charte_ceeac.institution', 'COMMISSION DE LA CEEAC');
    }

    public static function sousTitre(): string
    {
        return (string) config('charte_ceeac.sous_titre', 'Communauté Économique des États de l\'Afrique Centrale');
    }

    public static function systeme(): string
    {
        return (string) config('charte_ceeac.systeme', 'Système GEST-BUDGET-PAP');
    }

    public static function primaire(): string
    {
        return (string) config('charte_ceeac.couleurs.primaire', '#0a4a8f');
    }

    public static function fonce(): string
    {
        return (string) config('charte_ceeac.couleurs.fonce', '#073564');
    }

    public static function accent(): string
    {
        return (string) config('charte_ceeac.couleurs.accent', '#1a6fbf');
    }

    public static function fondTableau(): string
    {
        return (string) config('charte_ceeac.couleurs.fond_tableau', '#eef3f9');
    }

    public static function texteSecondaire(): string
    {
        return (string) config('charte_ceeac.couleurs.texte_secondaire', '#666666');
    }

    /** Hex sans # pour PhpWord / PhpSpreadsheet (ex. 0A4A8F). */
    public static function primaireHex(): string
    {
        return strtoupper(ltrim(self::primaire(), '#'));
    }

    public static function fonceHex(): string
    {
        return strtoupper(ltrim(self::fonce(), '#'));
    }

    public static function fondTableauHex(): string
    {
        return strtoupper(ltrim(self::fondTableau(), '#'));
    }

    public static function policePdf(): string
    {
        return (string) config('charte_ceeac.polices.pdf', 'DejaVu Sans, sans-serif');
    }

    public static function mentionPied(): string
    {
        return (string) config('charte_ceeac.pied_page.mention', 'Document généré par le système GEST-BUDGET-PAP');
    }

    public static function confidentialite(): string
    {
        return (string) config('charte_ceeac.pied_page.confidentialite', 'Usage interne institutionnel');
    }
}
