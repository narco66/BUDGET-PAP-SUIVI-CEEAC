<?php

namespace App\Rules;

use App\Services\Workflow\SodPolicyService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationException;

class RolesSodCompatibles implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        try {
            app(SodPolicyService::class)->assertRolesCompatibles($value);
        } catch (ValidationException $e) {
            $fail($e->validator->errors()->first('roles') ?? 'Rôles incompatibles (séparation des fonctions).');
        }
    }
}
