<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\NotificationType;
use App\Models\User;
use App\Events\NotificationCreee;
use App\Notifications\InstitutionInAppNotification;
use App\Support\NotificationCriticite;
use App\Support\NotificationNature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Point d'entrée centralisé — in-app, e-mail, SMS, audit (N-P0 → P3). */
class NotificationDispatcher
{
    public function __construct(
        private readonly EmailNotificationService $emails,
        private readonly SmsNotificationService $sms,
        private readonly NotificationPreferenceService $preferences,
        private readonly AuditNotificationService $audit,
    ) {}

    /**
     * @param  Collection<int, User>|User|iterable<User>  $destinataires
     * @param  array<string, scalar|null>  $variables
     * @param  array<string, mixed>  $meta
     */
    public function envoyer(
        Collection|User|iterable $destinataires,
        string $typeCode,
        string $titre,
        string $message,
        ?string $url = null,
        ?string $module = null,
        string $criticite = NotificationCriticite::INFORMATION,
        array $variables = [],
        array $meta = [],
        ?User $emetteur = null,
        string $canal = 'database',
        array $canaux = ['database'],
        ?string $nature = null,
        ?Model $ressource = null,
        bool $actionRequise = false,
        ?string $cleDeduplication = null,
    ): int {
        if (! $this->autoriseEnvoi($emetteur)) {
            throw new RuntimeException('Permission notifications.send requise pour émettre une notification.');
        }

        $url = self::lienRelatif($url);
        $type = NotificationType::where('code', $typeCode)->where('actif', true)->first();
        $module ??= $type?->module ?? 'systeme';
        $criticite = $type?->criticite_defaut ?? $criticite;
        $nature ??= $actionRequise ? NotificationNature::ACTION : NotificationNature::duType($typeCode);
        // État d'action : seules les notifications rattachées à une étape ou explicitement « à traiter » en portent un.
        $actionStatut = $actionRequise || (NotificationNature::porteUneAction($nature) && isset($meta['instance_id']))
            ? NotificationNature::ACTION_EN_ATTENTE
            : null;
        $colonnes = [
            'type_code' => $typeCode,
            'module' => null,
            'criticite' => $criticite,
            'nature' => $nature,
            'action_statut' => $actionStatut,
            'ressource_type' => $ressource?->getMorphClass(),
            'ressource_id' => $ressource?->getKey(),
            'reference' => $ressource ? (Str::limit((string) ($ressource->numero ?? $ressource->reference ?? ''), 60, '') ?: null) : null,
            'cle_deduplication' => $cleDeduplication,
        ];

        $template = $type
            ? NotificationTemplate::where('notification_type_id', $type->id)
                ->where('canal', $canal)
                ->where('actif', true)
                ->first()
            : null;

        if ($template) {
            $variables = array_merge([
                'titre' => $titre,
                'message' => $message,
                'url' => $url,
            ], $variables);
            $message = $template->appliquer($variables);
            if ($template->sujet) {
                // Le sujet est un gabarit distinct du corps : l'appliquer au corps remplaçait le titre par le message.
                $titre = $template->appliquer($variables, $template->sujet);
            }
        }

        $users = $this->normaliserDestinataires($destinataires);
        if ($cleDeduplication !== null) {
            $users = $users->reject(fn (User $u) => $this->dejaNotifie($u, $cleDeduplication))->values();
        }
        if ($users->isEmpty()) {
            return 0;
        }
        $colonnes['module'] = $module;

        $lignes = $users->map(fn (User $u) => [
            'user' => $u,
            'canaux' => $this->preferences->canauxEffectifs($u, $typeCode, $criticite, $canaux, $nature),
        ])->filter(fn (array $row) => $row['canaux'] !== []);

        if ($lignes->isEmpty()) {
            return 0;
        }

        $count = 0;

        DB::transaction(function () use ($lignes, $type, $typeCode, $module, $criticite, $titre, $message, $url, $variables, $meta, $emetteur, $colonnes, &$count) {
            foreach ($lignes as $row) {
                $user = $row['user'];
                $userCanaux = $row['canaux'];
                $laravelId = null;

                if (in_array('database', $userCanaux, true)) {
                    // Identifiant fixé avant l'envoi : le journal référence exactement la notification créée.
                    $notification = new InstitutionInAppNotification($typeCode, $module, $criticite, $titre, $message, $url, $meta);
                    $notification->id = (string) Str::uuid();
                    $user->notifyNow($notification);
                    $laravelId = $notification->id;
                    DB::table('notifications')->where('id', $laravelId)->update($colonnes);
                    $this->diffuserApresCommit($user, $laravelId, $colonnes, $titre, $message, $url);
                }

                $log = NotificationLog::create([
                    'notification_type_id' => $type?->id,
                    'type_code' => $typeCode,
                    'module' => $module,
                    'criticite' => $criticite,
                    'canal' => implode(',', $userCanaux),
                    'destinataire_type' => $user->getMorphClass(),
                    'destinataire_id' => $user->getKey(),
                    'emetteur_id' => $emetteur?->id,
                    'titre' => $titre,
                    'message' => $message,
                    'url' => $url,
                    'statut' => 'envoye',
                    'metadata' => $meta ?: null,
                    'laravel_notification_id' => $laravelId,
                    'envoye_le' => now(),
                ]);

                // Canaux externes après validation : la tâche e-mail retrouve son journal, un SMS n'est pas
                // envoyé pour une notification annulée.
                DB::afterCommit(function () use ($user, $userCanaux, $typeCode, $module, $titre, $message, $url, $variables, $meta, $emetteur, $log) {
                    if (in_array('email', $userCanaux, true) && $user->email) {
                        $this->emails->envoyer(
                            $user->email,
                            $typeCode,
                            $titre,
                            $message,
                            $variables,
                            $url,
                            $user,
                            module: $module,
                            meta: $meta,
                            notificationLogId: $log->id,
                            forceSync: app()->runningUnitTests(),
                        );
                        $this->audit->journaliser('envoi_email', $typeCode, $module, 'email', $emetteur, $user, $log->id, payload: $meta);
                    }

                    if (in_array('sms', $userCanaux, true) && $user->telephone) {
                        $this->sms->envoyer(
                            $user->telephone,
                            $typeCode,
                            $message,
                            array_merge($variables, ['titre' => $titre]),
                            $user,
                            $module,
                            $log->id,
                            $meta,
                        );
                        $this->audit->journaliser('envoi_sms', $typeCode, $module, 'sms', $emetteur, $user, $log->id, payload: $meta);
                    }
                });

                $this->audit->journaliser('envoi', $typeCode, $module, 'database', $emetteur, $user, $log->id, payload: $meta);

                $count++;
            }
        });

        return $count;
    }

    /**
     * Lien interne stocké sans hôte (« /besoins/12 ») : route() le rend absolu avec l'hôte courant
     * (APP_URL en console ou en file d'attente, port de développement…), ce qui le rendait mort
     * dès qu'on consultait l'application par une autre adresse.
     */
    public static function lienRelatif(?string $url): ?string
    {
        if ($url === null || ! preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $p = parse_url($url);

        return ($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '').(isset($p['fragment']) ? '#'.$p['fragment'] : '');
    }

    /** Même clé de déduplication déjà adressée au destinataire : action encore ouverte ou envoi récent (RN-10). */
    private function dejaNotifie(User $user, string $cle): bool
    {
        return $user->notifications()->where('cle_deduplication', $cle)
            ->where(fn ($q) => $q->where('action_statut', NotificationNature::ACTION_EN_ATTENTE)
                ->orWhere('created_at', '>=', now()->subMinutes(max(0, (int) config('notifications.deduplication_minutes', 10)))))
            ->exists();
    }

    /**
     * Diffusion temps réel après validation de la transaction, uniquement vers un diffuseur réel
     * (Reverb, Pusher…). Une panne de diffusion n'affecte ni la notification persistée ni l'opération métier.
     */
    private function diffuserApresCommit(User $user, string $id, array $colonnes, string $titre, string $message, ?string $url): void
    {
        if (! config('notifications.temps_reel.actif', true) || in_array(config('broadcasting.default'), [null, 'null', 'log'], true)) {
            return;
        }
        DB::afterCommit(function () use ($user, $id, $colonnes, $titre, $message, $url) {
            try {
                event(new NotificationCreee($user->id, [
                    'id' => $id,
                    'titre' => $titre,
                    'message' => Str::limit($message, 160),
                    'url' => $url,
                    'module' => $colonnes['module'],
                    'nature' => $colonnes['nature'],
                    'priorite' => NotificationCriticite::priorite($colonnes['criticite']),
                    'non_lues' => $user->unreadNotifications()->whereNull('archive_at')->count(),
                ]));
            } catch (\Throwable $e) {
                report($e);
                $this->audit->journaliser('echec_diffusion', $colonnes['type_code'], $colonnes['module'], 'broadcast', null, $user, payload: ['notification_id' => $id, 'erreur' => Str::limit($e->getMessage(), 300)]);
            }
        });
    }

    private function autoriseEnvoi(?User $emetteur): bool
    {
        if ($emetteur === null) {
            return true;
        }

        return $emetteur->can('notifications.send') || $emetteur->can('notifications.admin');
    }

    /** @return Collection<int, User> */
    private function normaliserDestinataires(Collection|User|iterable $destinataires): Collection
    {
        if ($destinataires instanceof User) {
            $destinataires = [$destinataires];
        }

        return collect($destinataires)
            ->filter(fn ($u) => $u instanceof User && $u->actif)
            ->unique('id')
            ->values();
    }
}
