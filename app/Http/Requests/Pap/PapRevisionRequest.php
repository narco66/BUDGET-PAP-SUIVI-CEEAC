<?php

namespace App\Http\Requests\Pap;

use Illuminate\Foundation\Http\FormRequest;

class PapRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'max:1000'],
        ];
    }
}
