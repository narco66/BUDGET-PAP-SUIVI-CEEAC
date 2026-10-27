<?php

namespace App\Services\Import\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Résultat de l'analyse d'une ligne : données normalisées, clé métier, opération proposée, cible existante,
 * différences avec la base et anomalies. Aucune écriture métier n'a lieu à ce stade.
 */
final class AnalyseLigne
{
    /** @var list<array{colonne: ?string, valeur: mixed, regle: string, gravite: string, message: string, suggestion: ?string}> */
    public array $anomalies = [];

    /** @var array<string, array{avant: mixed, apres: mixed}> */
    public array $differences = [];

    public string $operation = 'inserer';

    public ?Model $cible = null;

    public ?string $cle = null;

    /** @param array<string, mixed> $donnees */
    public function __construct(public array $donnees) {}

    public function anomalie(string $gravite, string $regle, string $message, ?string $colonne = null, mixed $valeur = null, ?string $suggestion = null): self
    {
        $this->anomalies[] = compact('colonne', 'valeur', 'regle', 'gravite', 'message', 'suggestion');

        return $this;
    }

    public function bloquante(string $regle, string $message, ?string $colonne = null, mixed $valeur = null, ?string $suggestion = null): self
    {
        return $this->anomalie('bloquante', $regle, $message, $colonne, $valeur, $suggestion);
    }

    public function majeure(string $regle, string $message, ?string $colonne = null, mixed $valeur = null, ?string $suggestion = null): self
    {
        return $this->anomalie('majeure', $regle, $message, $colonne, $valeur, $suggestion);
    }

    public function avertissement(string $regle, string $message, ?string $colonne = null, mixed $valeur = null, ?string $suggestion = null): self
    {
        return $this->anomalie('avertissement', $regle, $message, $colonne, $valeur, $suggestion);
    }

    public function aDesBloquantes(): bool
    {
        foreach ($this->anomalies as $a) {
            if ($a['gravite'] === 'bloquante') {
                return true;
            }
        }

        return false;
    }

    public function aDesMajeures(): bool
    {
        foreach ($this->anomalies as $a) {
            if ($a['gravite'] === 'majeure') {
                return true;
            }
        }

        return false;
    }

    public function difference(string $champ, mixed $avant, mixed $apres): self
    {
        $this->differences[$champ] = ['avant' => $avant, 'apres' => $apres];

        return $this;
    }
}
