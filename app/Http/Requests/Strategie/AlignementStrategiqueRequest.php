<?php

namespace App\Http\Requests\Strategie;

use App\Models\Activite;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AlignementStrategiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = [
            ProgrammeStrategique::class,
            ProjetStrategique::class,
            Activite::class,
        ];

        return [
            'vision_id' => ['nullable', 'exists:visions,id'],
            'orientation_strategique_id' => ['nullable', 'exists:orientations_strategiques,id'],
            'plan_strategique_id' => ['nullable', 'exists:plans_strategiques,id'],
            'alignable_type' => ['required', 'string', Rule::in($types)],
            'alignable_id' => ['required', 'integer'],
            'niveau_alignement' => ['required', 'in:direct,indirect,partiel'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('alignable_type');
            $id = $this->input('alignable_id');
            if ($type && $id && ! $type::find($id)) {
                $validator->errors()->add('alignable_id', 'Entité alignable introuvable.');
            }
        });
    }
}
