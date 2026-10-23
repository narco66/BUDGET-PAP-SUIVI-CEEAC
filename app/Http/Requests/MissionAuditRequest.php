<?php

namespace App\Http\Requests;

use App\Models\ConstatAudit;
use App\Models\MissionAudit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MissionAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'audit-missions.cloturer' => [
                'conclusion' => ['required', 'string'],
                'score_conformite' => ['nullable', 'integer', 'between:0,100'],
            ],
            'audit-missions.constats.store' => [
                'libelle' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'cause' => ['nullable', 'string'],
                'gravite' => ['required', Rule::in(array_keys(ConstatAudit::GRAVITES))],
                'recommandation' => ['nullable', 'string'],
                'responsable_user_id' => ['nullable', Rule::exists('users', 'id')],
                'echeance' => ['nullable', 'date'],
                'statut' => ['required', Rule::in(array_keys(ConstatAudit::STATUTS))],
                'taux_avancement' => ['nullable', 'integer', 'between:0,100'],
            ],
            'audit-missions.constats.update' => [
                'statut' => ['required', Rule::in(array_keys(ConstatAudit::STATUTS))],
                'taux_avancement' => ['required', 'integer', 'between:0,100'],
                'responsable_user_id' => ['nullable', Rule::exists('users', 'id')],
                'echeance' => ['nullable', 'date'],
            ],
            default => [
                'exercice_id' => ['nullable', Rule::exists('exercices', 'id')],
                'structure_id' => ['nullable', Rule::exists('structures', 'id')],
                'chef_mission_user_id' => ['nullable', Rule::exists('users', 'id')],
                'type' => ['required', Rule::in(array_keys(MissionAudit::TYPES))],
                'libelle' => ['required', 'string', 'max:255'],
                'domaine' => ['nullable', 'string', 'max:150'],
                'objectif' => ['nullable', 'string'],
                'perimetre' => ['nullable', 'string'],
                'date_debut' => ['nullable', 'date'],
                'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
                'statut' => ['required', Rule::in(array_keys(MissionAudit::STATUTS))],
            ],
        };
    }

    public function attributes(): array
    {
        return [
            'exercice_id' => 'exercice',
            'structure_id' => 'structure',
            'chef_mission_user_id' => 'chef de mission',
            'score_conformite' => 'score de conformité',
            'responsable_user_id' => 'responsable',
            'taux_avancement' => 'taux d\'avancement',
        ];
    }
}
