<?php

namespace App\Http\Requests\Pap;

use Illuminate\Foundation\Http\FormRequest;

class PapRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $user->can('create', \App\Models\Pap::class);
        }

        $pap = $this->route('pap');

        return $pap instanceof \App\Models\Pap && $user->can('update', $pap);
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'exercice_id' => ['required', 'exists:exercices,id', 'unique:paps,exercice_id'],
                'reference' => ['nullable', 'string', 'max:40', 'unique:paps,reference'],
                'libelle' => ['required', 'string', 'max:255'],
                'structure_id' => ['nullable', 'exists:structures,id'],
                'responsable_id' => ['nullable', 'exists:users,id'],
                'date_elaboration' => ['nullable', 'date'],
                'observations' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return [
            'libelle' => ['required', 'string', 'max:255'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'responsable_id' => ['nullable', 'exists:users,id'],
            'date_elaboration' => ['nullable', 'date'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'exercice_id' => 'exercice',
            'structure_id' => 'structure',
            'responsable_id' => 'responsable',
            'date_elaboration' => 'date d\'élaboration',
        ];
    }
}
