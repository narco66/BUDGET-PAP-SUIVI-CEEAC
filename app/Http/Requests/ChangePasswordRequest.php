<?php

namespace App\Http\Requests;

use App\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'mot_de_passe_actuel' => ['required', 'string', function ($attr, $value, $fail) {
                if (! Hash::check($value, $this->user()->password)) {
                    $fail('Le mot de passe actuel est incorrect.');
                }
            }],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers(), function ($attr, $value, $fail) {
                if (app(UserService::class)->motDePasseReutilise($this->user(), $value)) {
                    $fail('Ce mot de passe a déjà été utilisé récemment.');
                }
            }],
        ];
    }
}
