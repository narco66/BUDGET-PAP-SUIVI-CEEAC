<?php

namespace App\Http\Requests;

use App\Rules\RolesSodCompatibles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorisation via Policy au niveau du contrôleur
    }

    public function rules(): array
    {
        $id = $this->route('utilisateur')?->id;
        $creation = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'matricule' => ['nullable', 'string', 'max:30', Rule::unique('users', 'matricule')->ignore($id)],
            // mot de passe optionnel : si vide à la création, un mot de passe temporaire est généré
            'password' => [$creation ? 'nullable' : 'nullable', 'confirmed', Password::min(8)],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'fonction_id' => ['nullable', 'exists:fonctions,id'],
            'poste_id' => ['nullable', 'exists:postes,id'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'statut' => ['required', Rule::in(['actif', 'suspendu', 'desactive'])],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'roles' => ['array', new RolesSodCompatibles],
            'roles.*' => ['exists:roles,name'],
            'profils' => ['array'],
            'profils.*' => ['exists:profils,id'],
            'structures' => ['array'],
            'structures.*' => ['exists:structures,id'],
        ];
    }

    public function attributes(): array
    {
        return ['structure_id' => 'structure principale', 'poste_id' => 'poste'];
    }
}
