<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;

class PontRbmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orientation_strategique_id' => ['nullable', 'exists:orientations_strategiques,id'],
            'programme_strategique_id' => ['nullable', 'exists:programmes_strategiques,id'],
        ];
    }
}
