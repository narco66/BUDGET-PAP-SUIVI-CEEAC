<?php

namespace App\Services\Reporting;

use App\Models\Exercice;
use InvalidArgumentException;

/** Résolution et validation des périodes de reporting (annuel, trimestriel, semestriel, mensuel). */
class RapportPeriodeService
{
    public function trimestreCourant(int $annee): string
    {
        $t = (int) ceil(now()->month / 3);

        return "{$annee}-T{$t}";
    }

    public function semestreCourant(int $annee): string
    {
        return now()->month <= 6 ? "{$annee}-S1" : "{$annee}-S2";
    }

    public function libelle(string $periode): string
    {
        if (preg_match('/^(\d{4})-T([1-4])$/', $periode, $m)) {
            return 'Trimestre T'.$m[2].' '.$m[1];
        }
        if (preg_match('/^(\d{4})-S([12])$/', $periode, $m)) {
            return 'Semestre S'.$m[2].' '.$m[1];
        }
        if (preg_match('/^(\d{4})-M(\d{2})$/', $periode, $m)) {
            return 'Mois '.$m[2].'/'.$m[1];
        }

        return "Annuel {$periode}";
    }

    /** @return array<string, string> */
    public function optionsTrimestrielles(int $annee): array
    {
        $options = [];
        for ($t = 1; $t <= 4; $t++) {
            $options["{$annee}-T{$t}"] = "Trimestre T{$t} — {$annee}";
        }

        return $options;
    }

    /** @return array<string, string> */
    public function optionsSemestrielles(int $annee): array
    {
        return [
            "{$annee}-S1" => "Semestre S1 — {$annee}",
            "{$annee}-S2" => "Semestre S2 — {$annee}",
        ];
    }

    /** @return array<string, string> */
    public function optionsAnnuelles(int $annee): array
    {
        return [(string) $annee => "Annuel {$annee}"];
    }

    /** @return array<string, string> */
    public function optionsPourTypeRapport(int $annee, string $typeRapport): array
    {
        return match ($typeRapport) {
            'trimestriel' => $this->optionsTrimestrielles($annee),
            'semestriel' => $this->optionsSemestrielles($annee),
            'annuel' => $this->optionsAnnuelles($annee),
            default => $this->optionsPourExercice($annee),
        };
    }
    /** @return array{periode:string, periode_type:string, periode_debut:string, periode_fin:string} */
    public function resoudre(Exercice $exercice, ?string $saisie = null): array
    {
        $annee = (int) $exercice->annee;
        $input = trim((string) ($saisie ?: $annee));

        if ($input === (string) $annee) {
            return $this->pack($input, 'annuel', "{$annee}-01-01", "{$annee}-12-31");
        }

        if (preg_match('/^(\d{4})-T([1-4])$/', $input, $m)) {
            $this->assertAnnee((int) $m[1], $annee);
            $t = (int) $m[2];

            return $this->pack($input, 'trimestriel', ...match ($t) {
                1 => ["{$annee}-01-01", "{$annee}-03-31"],
                2 => ["{$annee}-04-01", "{$annee}-06-30"],
                3 => ["{$annee}-07-01", "{$annee}-09-30"],
                4 => ["{$annee}-10-01", "{$annee}-12-31"],
            });
        }

        if (preg_match('/^(\d{4})-S([12])$/', $input, $m)) {
            $this->assertAnnee((int) $m[1], $annee);
            $s = (int) $m[2];

            return $this->pack($input, 'semestriel', ...match ($s) {
                1 => ["{$annee}-01-01", "{$annee}-06-30"],
                2 => ["{$annee}-07-01", "{$annee}-12-31"],
            });
        }

        if (preg_match('/^(\d{4})-M(0[1-9]|1[0-2])$/', $input, $m)) {
            $this->assertAnnee((int) $m[1], $annee);
            $mois = (int) substr($m[2], 0, 2);
            $debut = sprintf('%04d-%02d-01', $annee, $mois);
            $fin = date('Y-m-t', strtotime($debut));

            return $this->pack($input, 'mensuel', $debut, $fin);
        }

        throw new InvalidArgumentException("Période invalide « {$input} » pour l'exercice {$annee}.");
    }

    /** @return array<string, string> */
    public function optionsPourExercice(int $annee): array
    {
        $options = [(string) $annee => "Annuel {$annee}"];

        for ($t = 1; $t <= 4; $t++) {
            $options["{$annee}-T{$t}"] = "Trimestre T{$t} — {$annee}";
        }
        for ($s = 1; $s <= 2; $s++) {
            $options["{$annee}-S{$s}"] = "Semestre S{$s} — {$annee}";
        }

        return $options;
    }

    private function assertAnnee(int $periodeAnnee, int $exerciceAnnee): void
    {
        if ($periodeAnnee !== $exerciceAnnee) {
            throw new InvalidArgumentException("La période doit correspondre à l'exercice {$exerciceAnnee}.");
        }
    }

    private function pack(string $periode, string $type, string $debut, string $fin): array
    {
        return [
            'periode' => $periode,
            'periode_type' => $type,
            'periode_debut' => $debut,
            'periode_fin' => $fin,
        ];
    }
}
