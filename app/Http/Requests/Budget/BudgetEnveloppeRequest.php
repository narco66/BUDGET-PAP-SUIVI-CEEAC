<?php

namespace App\Http\Requests\Budget;

use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;

class BudgetEnveloppeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            return $user->can('create', Budget::class);
        }

        $budget = $this->route('budget');

        return $budget instanceof Budget && $user->can('update', $budget);
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'exercice_id' => ['required', 'exists:exercices,id', 'unique:budgets,exercice_id'],
                'reference' => ['nullable', 'string', 'max:40', 'unique:budgets,reference'],
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
            'date_elaboration' => "date d'élaboration",
        ];
    }
}
