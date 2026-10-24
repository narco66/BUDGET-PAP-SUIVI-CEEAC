<?php

namespace App\Services\Api;

use App\Services\AccessScopeService;
use App\Services\Auth\MfaService;
use App\Support\SanctumContexte;
use Illuminate\Http\Request;

class ProfilApiService
{
    public function __construct(
        private readonly AccessScopeService $scopes,
        private readonly MfaService $mfa,
    ) {}

    /** @return array<string, mixed> */
    public function pour(Request $request): array
    {
        $user = $request->user()->loadMissing('structure');
        $token = $user->currentAccessToken();
        $session = SanctumContexte::estSession(is_object($token) ? $token : null);
        $abilities = ['session'];
        if (! $session && is_object($token) && isset($token->abilities) && is_array($token->abilities)) {
            $abilities = $token->abilities;
        }

        return [
            'id' => $user->id,
            'nom' => $user->name,
            'email' => $user->email,
            'structure_id' => $user->structure_id,
            'structure_code' => $user->structure?->code,
            'structures_autorisees' => $this->scopes->structuresAutorisees($user)->values(),
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'est_administrateur' => $user->estAdministrateur(),
            'abilities' => $abilities,
            'connected_app_id' => SanctumContexte::connectedAppId(is_object($token) ? $token : null),
            'mode_auth' => $session ? 'session' : 'token',
            'doit_changer_mot_de_passe' => (bool) $user->doit_changer_mot_de_passe,
            'mfa_active' => $this->mfa->aMfaActive($user),
            'mfa_obligatoire' => $this->mfa->estObligatoire($user),
            'locale' => 'fr',
            'devise' => 'XAF',
        ];
    }
}
