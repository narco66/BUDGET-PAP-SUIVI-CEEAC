<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $abilitiesList = implode(',', config('interoperabilite.token_abilities', ['lecture', 'suivi', 'tompro']));

        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'nom' => ['nullable', 'string', 'max:100'],
            'abilities' => ['array'],
            'abilities.*' => ['string', 'in:'.$abilitiesList],
            'client_id' => ['nullable', 'string', 'max:64'],
            'client_secret' => ['nullable', 'string', 'required_with:client_id'],
        ];
    }
}
