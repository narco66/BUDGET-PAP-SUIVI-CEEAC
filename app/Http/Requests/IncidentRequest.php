<?php

namespace App\Http\Requests;

use App\Models\Incident;
use App\Models\IncidentAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'incidents.cloturer' => [
                'resolution' => ['required', 'string'],
                'cause_racine' => ['nullable', 'string'],
            ],
            'incidents.rejeter' => [
                'motif' => ['required', 'string', 'max:500'],
            ],
            'incidents.avancer' => [
                'observation' => ['nullable', 'string', 'max:500'],
                'assigne_a' => ['nullable', Rule::exists('users', 'id')],
            ],
            'incidents.actions.store' => [
                'type' => ['required', Rule::in(array_keys(IncidentAction::TYPES))],
                'libelle' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
            ],
            default => [
                'incident_categorie_id' => ['nullable', Rule::exists('incident_categories', 'id')],
                'structure_id' => ['nullable', Rule::exists('structures', 'id')],
                'assigne_a' => ['nullable', Rule::exists('users', 'id')],
                'titre' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'gravite' => ['required', Rule::in(array_keys(Incident::GRAVITES))],
                'source' => ['required', Rule::in(array_keys(Incident::SOURCES))],
                'impact' => ['nullable', 'string'],
            ],
        };
    }

    public function attributes(): array
    {
        return [
            'incident_categorie_id' => 'catégorie',
            'structure_id' => 'structure',
            'assigne_a' => 'assigné à',
            'cause_racine' => 'cause racine',
        ];
    }
}
