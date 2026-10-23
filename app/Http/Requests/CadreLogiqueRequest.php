<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CadreLogiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cadre_logique.gerer')
            || $this->user()?->can('rbm.admin')
            || $this->user()?->can('rbm.modifier')
            || false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'intitule' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'axes' => ['nullable', 'array'],
            'axes.*.id' => ['required', 'integer', 'exists:axes,id'],
            'axes.*.objectif_strategique' => ['nullable', 'string', 'max:5000'],
            'axes.*.commentaire' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
