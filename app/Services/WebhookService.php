<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookLivraison;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Déclenche et livre les webhooks abonnés (D12 — EF-D12-03, enrichi I-P2). */
class WebhookService
{
    public function declencher(string $evenement, array $payload): void
    {
        $enveloppe = [
            'evenement' => $evenement,
            'donnees' => $payload,
            'horodatage' => now()->toIso8601String(),
        ];

        foreach (Webhook::pour($evenement)->get() as $webhook) {
            $livraison = $webhook->livraisons()->create([
                'evenement' => $evenement,
                'payload' => $enveloppe,
                'statut' => 'en_attente',
                'tentatives' => 0,
                'succes' => false,
                'created_at' => now(),
            ]);

            DeliverWebhookJob::dispatch($livraison->id);
        }
    }

    /** Tente une livraison (appelée par le job ou les relances). */
    public function livrer(int $livraisonId): void
    {
        $livraison = WebhookLivraison::with('webhook')->find($livraisonId);
        if (! $livraison || ! $livraison->webhook?->actif || $livraison->statut === 'livre') {
            return;
        }

        $max = config('interoperabilite.webhooks.max_tentatives', 5);
        if ($livraison->tentatives >= $max) {
            $livraison->update(['statut' => 'echec', 'erreur' => 'Nombre maximal de tentatives atteint.']);

            return;
        }

        $webhook = $livraison->webhook;
        $enveloppe = $livraison->payload ?? [];
        $statutHttp = null;
        $erreur = null;

        try {
            $requete = Http::timeout(config('interoperabilite.webhooks.timeout', 10))
                ->withHeaders(['X-Signature' => $this->signature($enveloppe, $webhook->secret)]);

            $reponse = match (strtoupper($webhook->methode ?? 'POST')) {
                'PUT' => $requete->put($webhook->url, $enveloppe),
                default => $requete->post($webhook->url, $enveloppe),
            };

            $statutHttp = $reponse->status();

            if ($reponse->successful()) {
                $livraison->update([
                    'statut_http' => $statutHttp,
                    'succes' => true,
                    'statut' => 'livre',
                    'erreur' => null,
                    'prochaine_tentative_le' => null,
                    'tentatives' => $livraison->tentatives + 1,
                ]);

                return;
            }

            $erreur = "HTTP {$statutHttp}";
        } catch (Throwable $e) {
            $erreur = $e->getMessage();
        }

        $tentatives = $livraison->tentatives + 1;
        $echecDefinitif = $tentatives >= $max;

        $livraison->update([
            'statut_http' => $statutHttp,
            'succes' => false,
            'statut' => $echecDefinitif ? 'echec' : 'en_attente',
            'tentatives' => $tentatives,
            'erreur' => $erreur,
            'prochaine_tentative_le' => $echecDefinitif ? null : now()->addSeconds($this->delaiBackoff($tentatives)),
        ]);
    }

    /** Envoie un événement de test vers un webhook spécifique. */
    public function tester(Webhook $webhook): WebhookLivraison
    {
        $enveloppe = [
            'evenement' => $webhook->evenement,
            'donnees' => ['test' => true, 'message' => 'Ping interopérabilité CEEAC'],
            'horodatage' => now()->toIso8601String(),
        ];

        $livraison = $webhook->livraisons()->create([
            'evenement' => $webhook->evenement,
            'payload' => $enveloppe,
            'statut' => 'en_attente',
            'tentatives' => 0,
            'succes' => false,
            'created_at' => now(),
        ]);

        $this->livrer($livraison->id);

        return $livraison->fresh();
    }

    private function delaiBackoff(int $tentative): int
    {
        $delais = config('interoperabilite.webhooks.backoff_seconds', [60, 300, 900, 3600, 14400]);

        return (int) ($delais[$tentative - 1] ?? end($delais));
    }

    private function signature(array $enveloppe, ?string $secret): string
    {
        return hash_hmac('sha256', json_encode($enveloppe), (string) $secret);
    }
}
