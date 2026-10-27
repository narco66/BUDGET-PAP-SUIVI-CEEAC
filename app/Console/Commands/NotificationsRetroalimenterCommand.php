<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationActionResolver;
use App\Support\NotificationNature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Renseigne la nature, l'état d'action et le dossier des notifications antérieures au modèle enrichi,
 * d'après leur type, leur méta et leur lien. Ne modifie ni le message ni la lecture ; seul un titre écrasé par
 * le message (défaut corrigé du dispatcher) est remplacé par le libellé du type. Idempotent.
 */
class NotificationsRetroalimenterCommand extends Command
{
    protected $signature = 'notifications:retroalimenter {--simulation : affiche le bilan sans écrire}';

    protected $description = 'Complète nature, état d\'action et dossier lié des notifications existantes';

    public function handle(NotificationActionResolver $resolver): int
    {
        $bilan = ['traitees' => 0, 'natures' => [], 'etats' => [], 'ressources' => 0];

        DB::table('notifications')->orderBy('created_at')->orderBy('id')
            ->chunk(200, function ($lot) use ($resolver, &$bilan) {
                foreach ($lot as $n) {
                    $data = is_string($n->data) ? (json_decode($n->data, true) ?: []) : (array) $n->data;
                    $nature = NotificationNature::duType((string) ($n->type_code ?? $data['type_code'] ?? ''));
                    $etat = null;
                    if (NotificationNature::porteUneAction($nature)) {
                        $etat = ! empty($data['meta']['tache_traitee'])
                            ? NotificationNature::ACTION_TRAITEE
                            : ($resolver->etatReel((array) ($data['meta'] ?? [])) ?? NotificationNature::ACTION_TRAITEE);
                    }
                    $ressource = $n->ressource_type ? null : NotificationActionResolver::ressourceDepuisUrl($data['url'] ?? null);
                    $maj = ['nature' => $nature, 'action_statut' => $etat];
                    if ($etat && $etat !== NotificationNature::ACTION_EN_ATTENTE) {
                        $maj['action_traitee_le'] = $n->action_traitee_le ?? $data['meta']['traitee_le'] ?? $n->read_at ?? $n->created_at;
                    }
                    if ($ressource) {
                        $objet = $ressource[0]::query()->find($ressource[1]);
                        $maj += ['ressource_type' => $ressource[0], 'ressource_id' => $ressource[1], 'reference' => $objet?->numero ?? $objet?->reference];
                        $bilan['ressources']++;
                    }
                    // Titre écrasé par le corps du message (défaut corrigé du dispatcher) : libellé du type à la place.
                    if (($data['titre'] ?? null) !== null && ($data['titre'] ?? null) === ($data['message'] ?? null) && $n->type_code) {
                        $libelle = DB::table('notification_types')->where('code', $n->type_code)->value('libelle');
                        if ($libelle) {
                            $data['titre'] = $libelle;
                            $maj['data'] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $bilan['titres'] = ($bilan['titres'] ?? 0) + 1;
                        }
                    }
                    $bilan['natures'][$nature] = ($bilan['natures'][$nature] ?? 0) + 1;
                    $bilan['etats'][$etat ?? '—'] = ($bilan['etats'][$etat ?? '—'] ?? 0) + 1;
                    $bilan['traitees']++;
                    if (! $this->option('simulation')) {
                        DB::table('notifications')->where('id', $n->id)->update($maj);
                    }
                }
            });

        $this->info(($this->option('simulation') ? '[simulation] ' : '')."Notifications examinées : {$bilan['traitees']} ; dossier lié retrouvé : {$bilan['ressources']}.");
        $this->line('Natures : '.json_encode($bilan['natures'], JSON_UNESCAPED_UNICODE));
        $this->line('États d\'action : '.json_encode($bilan['etats'], JSON_UNESCAPED_UNICODE));
        $this->line('Titres réparés : '.($bilan['titres'] ?? 0));

        return self::SUCCESS;
    }
}
