<?php

namespace App\Services\Workflow;

use App\Models\WorkflowCondition;
use Illuminate\Database\Eloquent\Model;

/** Évalue les expressions JSON de branchement conditionnel (P3). */
class WorkflowConditionEvaluator
{
    public function satisfait(?WorkflowCondition $condition, ?Model $objet): bool
    {
        if ($condition === null || ! $condition->actif) {
            return true;
        }

        $expr = $condition->expression;
        if (! is_array($expr) || empty($expr['champ'])) {
            return true;
        }

        $valeurObjet = data_get($objet, $expr['champ']);
        $operateur = $expr['operateur'] ?? '==';
        $attendu = $expr['valeur'] ?? null;

        return match ($operateur) {
            '==' => $valeurObjet == $attendu,
            '!=' => $valeurObjet != $attendu,
            '>' => (float) $valeurObjet > (float) $attendu,
            '>=' => (float) $valeurObjet >= (float) $attendu,
            '<' => (float) $valeurObjet < (float) $attendu,
            '<=' => (float) $valeurObjet <= (float) $attendu,
            'in' => in_array($valeurObjet, (array) $attendu, true),
            default => false,
        };
    }
}
