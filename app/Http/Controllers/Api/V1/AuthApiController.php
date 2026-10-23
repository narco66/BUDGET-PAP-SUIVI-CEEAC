<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\ApiTokenRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\Interop\ConnectedAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function __construct(private readonly ConnectedAppService $connectedApps) {}

    /** Émet un jeton d'accès personnel avec scopes (EF-D12-02, enrichi I-P1). */
    public function token(ApiTokenRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->actif) {
            throw ValidationException::withMessages(['email' => 'Identifiants invalides ou compte inactif.']);
        }

        if (! $user->can('api.create') && ! $user->can('api.admin')) {
            throw ValidationException::withMessages(['email' => 'Ce compte n\'est pas autorisé à émettre des jetons API.']);
        }

        $abilities = $data['abilities'] ?? ['lecture'];
        $connectedAppId = null;
        $apiClientId = null;

        if (! empty($data['client_id'])) {
            $client = $this->connectedApps->validerClient($data['client_id'], $data['client_secret'] ?? '');
            if (! $client) {
                throw ValidationException::withMessages(['client_id' => 'Identifiants applicatifs invalides ou révoqués.']);
            }

            $abilities = $this->connectedApps->intersecterScopes($client->connectedApp, $abilities);
            $connectedAppId = $client->connected_app_id;
            $apiClientId = $client->id;
            $request->attributes->set('connected_app_id', $connectedAppId);
        }

        $expiresAt = ($minutes = config('sanctum.expiration'))
            ? now()->addMinutes((int) $minutes)
            : null;

        $token = $user->createToken($data['nom'] ?? 'api', $abilities, $expiresAt);
        $token->accessToken->forceFill([
            'connected_app_id' => $connectedAppId,
            'api_client_id' => $apiClientId,
        ])->save();

        return response()->json([
            'token' => $token->plainTextToken,
            'type' => 'Bearer',
            'abilities' => $abilities,
            'connected_app_id' => $connectedAppId,
            'expires_at' => $expiresAt?->toIso8601String(),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('structure');

        return response()->json([
            'id' => $user->id,
            'nom' => $user->name,
            'email' => $user->email,
            'structure_id' => $user->structure_id,
            'structure_code' => $user->structure?->code,
            'structures_autorisees' => app(AccessScopeService::class)->structuresAutorisees($user)->values(),
            'roles' => $user->getRoleNames(),
            'abilities' => $user->currentAccessToken()->abilities ?? [],
            'connected_app_id' => $user->currentAccessToken()->connected_app_id,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Jeton révoqué.']);
    }
}
