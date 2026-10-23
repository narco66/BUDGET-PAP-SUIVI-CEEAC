<?php

namespace App\Http\Requests;

use App\Services\Institution\StructureHierarchieService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorisation gérée par la Policy sur la route/contrôleur
    }

    public function rules(): array
    {
        $id = $this->route('structure')?->id;

        $reglesParent = ['nullable', 'exists:structures,id'];
        if ($id) {
            $reglesParent[] = Rule::notIn([$id]);
        }

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('structures', 'code')->ignore($id)],
            'libelle' => ['required', 'string', 'max:255'],
            'sigle' => ['nullable', 'string', 'max:30'],
            'type' => ['required', Rule::in([
                'presidence', 'vice_presidence', 'secretariat_general',
                'commissariat', 'departement', 'direction', 'service', 'cellule',
            ])],
            'parent_id' => $reglesParent,
            'responsable_user_id' => ['nullable', 'exists:users,id'],
            'ordre' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'actif' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $message = app(StructureHierarchieService::class)->valider(
                (string) $this->input('type'),
                $this->input('parent_id') ? (int) $this->input('parent_id') : null,
                $this->route('structure')?->id,
            );

            if ($message) {
                $validator->errors()->add('parent_id', $message);
            }
        });
    }

    public function attributes(): array
    {
        return [
            'code' => 'code', 'libelle' => 'libellé', 'parent_id' => 'structure parente',
            'responsable_user_id' => 'responsable',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['actif' => $this->boolean('actif')]);
    }
}
