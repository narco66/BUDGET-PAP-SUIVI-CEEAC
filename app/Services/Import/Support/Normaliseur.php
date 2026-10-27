<?php

namespace App\Services\Import\Support;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateExcel;

/**
 * Normalisation contrôlée d'une valeur selon le type du champ : espaces, codes, nombres, dates, booléens.
 * Aucune conversion monétaire n'est faite : une devise autre que XAF/FCFA est refusée, jamais convertie.
 * Retourne [valeur normalisée, message d'erreur|null].
 */
final class Normaliseur
{
    /** @return array{0: mixed, 1: ?string} */
    public static function normaliser(mixed $valeur, string $type): array
    {
        if ($valeur === null || (is_string($valeur) && trim($valeur) === '')) {
            return [null, null];
        }

        return match ($type) {
            'code' => [mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $valeur))), null],
            'entier' => self::entier($valeur),
            'montant', 'decimal' => self::nombre($valeur, $type),
            'date' => self::date($valeur),
            'booleen' => self::booleen($valeur),
            'email' => self::email($valeur),
            default => [preg_replace('/\s+/u', ' ', trim((string) $valeur)), null],
        };
    }

    /** Valeur susceptible d'être interprétée comme une formule par un tableur (injection CSV). */
    public static function estFormule(mixed $valeur): bool
    {
        return is_string($valeur) && preg_match('/^\s*[=+\-@\t\r]/u', $valeur) === 1 && ! is_numeric(trim($valeur));
    }

    /** @return array{0: ?int, 1: ?string} */
    private static function entier(mixed $valeur): array
    {
        $texte = str_replace([' ', "\u{00A0}", "\u{202F}"], '', (string) $valeur);

        return preg_match('/^-?\d+$/', $texte) === 1 ? [(int) $texte, null] : [null, 'Nombre entier attendu.'];
    }

    /** @return array{0: ?string, 1: ?string} montant décimal sous forme de chaîne (précision conservée) */
    private static function nombre(mixed $valeur, string $type): array
    {
        if (is_int($valeur) || is_float($valeur)) {
            return [self::formater((float) $valeur), null];
        }
        $texte = trim((string) $valeur);
        if (preg_match('/(€|\$|EUR|USD|£|GBP)/iu', $texte)) {
            return [null, 'Devise étrangère détectée : aucune conversion n\'est effectuée, saisir le montant en XAF.'];
        }
        $texte = preg_replace('/\s|\x{00A0}|\x{202F}|XAF|FCFA|F\s?CFA/iu', '', $texte);
        // « 1.234.567,50 » ou « 1,234,567.50 » : le dernier séparateur est décimal.
        if (preg_match('/^-?[\d.,]+$/', $texte) !== 1) {
            return [null, $type === 'montant' ? 'Montant non numérique.' : 'Nombre attendu.'];
        }
        $dernierPoint = strrpos($texte, '.');
        $derniereVirgule = strrpos($texte, ',');
        $decimal = max($dernierPoint === false ? -1 : $dernierPoint, $derniereVirgule === false ? -1 : $derniereVirgule);
        if ($decimal >= 0 && strlen($texte) - $decimal - 1 === 3 && substr_count($texte, $texte[$decimal]) > 1) {
            $decimal = -1;   // séparateurs de milliers uniquement (1.234.567)
        }
        $entier = $decimal >= 0 ? substr($texte, 0, $decimal) : $texte;
        $fraction = $decimal >= 0 ? substr($texte, $decimal + 1) : '';
        $entier = str_replace(['.', ','], '', $entier);
        if ($entier === '' || $entier === '-' || ! is_numeric($entier.($fraction !== '' ? '.'.$fraction : ''))) {
            return [null, $type === 'montant' ? 'Montant non numérique.' : 'Nombre attendu.'];
        }

        return [self::formater((float) ($entier.($fraction !== '' ? '.'.$fraction : ''))), null];
    }

    private static function formater(float $n): string
    {
        return number_format($n, 2, '.', '');
    }

    /** @return array{0: ?string, 1: ?string} date ISO (AAAA-MM-JJ) */
    private static function date(mixed $valeur): array
    {
        try {
            if (is_numeric($valeur) && (float) $valeur > 20000 && (float) $valeur < 80000) {
                return [Carbon::instance(DateExcel::excelToDateTimeObject((float) $valeur))->toDateString(), null];
            }
            $texte = trim((string) $valeur);
            foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'Y/m/d'] as $format) {
                $d = Carbon::createFromFormat('!'.$format, $texte);
                if ($d !== false && $d->format($format) === $texte) {
                    return [$d->toDateString(), null];
                }
            }
        } catch (\Throwable) {
        }

        return [null, 'Date invalide (formats acceptés : JJ/MM/AAAA, AAAA-MM-JJ).'];
    }

    /** @return array{0: ?bool, 1: ?string} */
    private static function booleen(mixed $valeur): array
    {
        $v = mb_strtolower(trim((string) $valeur));

        return match (true) {
            in_array($v, ['1', 'oui', 'o', 'vrai', 'true', 'yes', 'actif', 'active', 'x'], true) => [true, null],
            in_array($v, ['0', 'non', 'n', 'faux', 'false', 'no', 'inactif', 'inactive'], true) => [false, null],
            default => [null, 'Valeur oui/non attendue.'],
        };
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function email(mixed $valeur): array
    {
        $v = mb_strtolower(trim((string) $valeur));

        return filter_var($v, FILTER_VALIDATE_EMAIL) ? [$v, null] : [null, 'Adresse électronique invalide.'];
    }
}
