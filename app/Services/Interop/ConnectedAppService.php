<?php

namespace App\Services\Interop;

use App\Models\ApiClient;
use App\Models\ConnectedApp;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Gestion du registre applications connectées et clients API (I-P1). */
class ConnectedAppService
{
    /** @return array{client: ApiClient, client_id: string, client_secret: string} */
    public function creerClient(ConnectedApp $app, string $libelle = 'Production'): array
    {
        if (! $app->estActive()) {
            throw ValidationException::withMessages(['statut' => 'Impossible de créer un client pour une application inactive.']);
        }

        $clientId = 'ceeac_'.Str::lower(Str::random(16));
        $secret = Str::random(48);

        $client = $app->clients()->create([
            'client_id' => $clientId,
            'client_secret_hash' => Hash::make($secret),
            'libelle' => $libelle,
            'statut' => 'actif',
        ]);

        return [
            'client' => $client,
            'client_id' => $clientId,
            'client_secret' => $secret,
        ];
    }

    public function validerClient(string $clientId, string $secret): ?ApiClient
    {
        $client = ApiClient::with('connectedApp')
            ->where('client_id', $clientId)
            ->first();

        if (! $client || ! $client->estActif() || ! $client->connectedApp?->estActive()) {
            return null;
        }

        if (! Hash::check($secret, $client->client_secret_hash)) {
            return null;
        }

        $client->update(['derniere_utilisation_le' => now()]);

        return $client->fresh(['connectedApp']);
    }

    /** @param  array<int, string>  $demandes */
    public function intersecterScopes(ConnectedApp $app, array $demandes): array
    {
        $autorises = array_values(array_intersect(
            $demandes ?: ['lecture'],
            $app->scopes_autorises ?? []
        ));

        if ($autorises === []) {
            throw ValidationException::withMessages([
                'abilities' => 'Aucun scope demandé n\'est autorisé pour cette application.',
            ]);
        }

        return $autorises;
    }

    public function revoquerClient(ApiClient $client, User $par): void
    {
        $client->update([
            'statut' => 'revoque',
            'revoked_at' => now(),
            'revoked_by' => $par->id,
        ]);

        PersonalAccessToken::query()
            ->where('api_client_id', $client->id)
            ->delete();
    }

    public function revoquerJetonsApplication(ConnectedApp $app): int
    {
        return PersonalAccessToken::query()
            ->where('connected_app_id', $app->id)
            ->delete();
    }

    public function suspendreApplication(ConnectedApp $app, User $par): void
    {
        $app->update(['statut' => 'suspendu']);

        $app->clients()->where('statut', 'actif')->each(
            fn (ApiClient $client) => $this->revoquerClient($client, $par)
        );
    }

    public function reactiverApplication(ConnectedApp $app): void
    {
        if ($app->estRevokee()) {
            throw ValidationException::withMessages(['statut' => 'Une application révoquée ne peut pas être réactivée.']);
        }

        $app->update(['statut' => 'actif']);
    }
}
