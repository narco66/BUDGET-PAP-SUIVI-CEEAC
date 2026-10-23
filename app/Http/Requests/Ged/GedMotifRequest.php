<?php

namespace App\Http\Requests\Ged;

use Illuminate\Foundation\Http\FormRequest;

class GedMotifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'max:255'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ];
    }
}
