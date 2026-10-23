<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/** Règles de validation communes des décisions workflow (P0). */
final class WorkflowDecisionRules
{
    /** @return array<string, mixed> */
    public static function regles(bool $avecRenvoi = true): array
    {
        $decisions = $avecRenvoi
            ? ['valider', 'rejeter', 'renvoyer']
            : ['valider', 'rejeter'];

        return [
            'decision' => ['required', Rule::in($decisions)],
            'observation' => ['nullable', 'string', 'max:1000', 'required_unless:decision,valider'],
            'categorie_rejet' => ['nullable', 'required_if:decision,rejeter', Rule::in(WorkflowRejetCategorie::values())],
        ];
    }
}
