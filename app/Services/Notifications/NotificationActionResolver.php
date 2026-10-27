<?php

namespace App\Services\Notifications;

use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTache;
use App\Services\Ged\GedAttachmentService;
use App\Support\NotificationNature;
use App\Support\WorkflowObjetUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Destination sûre d'une notification (référentiel § 9) et état réel de son action (§ 10, RN-06, RN-16).
 * La notification n'ouvre aucun droit : la page cible applique ses propres contrôles d'habilitation.
 */
class NotificationActionResolver
{
    /**
     * @return array{url: string, action_disponible: bool, message: ?string}
     */
    public function destination(DatabaseNotification $notification, User $user): array
    {
        $etat = $this->actualiserEtat($notification);
        $url = (string) data_get($notification->data, 'url', '');
        $ressource = $this->ressource($notification);
        $message = null;

        if ($etat !== null && $etat !== NotificationNature::ACTION_EN_ATTENTE) {
            $par = data_get($notification->data, 'meta.traitee_par');
            $message = match ($etat) {
                NotificationNature::ACTION_TRAITEE => 'Ce dossier a déjà été traité'.($par ? " par {$par}" : '').' : l\'action demandée n\'est plus disponible.',
                NotificationNature::ACTION_ANNULEE => 'Cette action a été annulée (circuit clôturé ou étape devenue sans objet).',
                default => 'Cette action a expiré.',
            };
            // La fiche de traitement d'une tâche close laisse place au dossier lui-même, consultable selon les droits.
            if ($ressource && str_starts_with($url, '/mes-taches/')) {
                $url = (string) NotificationDispatcher::lienRelatif(WorkflowObjetUrls::show($ressource));
            }
        }
        if (! $this->estInterne($url) && $ressource) {
            $url = (string) NotificationDispatcher::lienRelatif(WorkflowObjetUrls::show($ressource));
        }

        return [
            'url' => $this->estInterne($url) ? $url : route('notifications.index', absolute: false),
            'action_disponible' => $etat === NotificationNature::ACTION_EN_ATTENTE,
            'message' => $message,
        ];
    }

    /**
     * État d'action recalculé sur l'état réel du dossier : une action restée « en attente » alors que
     * sa tâche est close (ou son circuit terminé) est clôturée à la volée.
     */
    public function actualiserEtat(DatabaseNotification $notification): ?string
    {
        $etat = $notification->action_statut;
        if ($etat !== NotificationNature::ACTION_EN_ATTENTE) {
            return $etat;
        }
        $reel = $this->etatReel((array) data_get($notification->data, 'meta', []));
        if ($reel !== null && $reel !== NotificationNature::ACTION_EN_ATTENTE) {
            $notification->forceFill(['action_statut' => $reel, 'action_traitee_le' => now()])->save();
        }

        return $reel ?? $etat;
    }

    /** État d'action déduit de la tâche ou de l'étape de workflow référencée par la méta. */
    public function etatReel(array $meta): ?string
    {
        $tache = null;
        if (! empty($meta['tache_id'])) {
            $tache = WorkflowTache::query()->find((int) $meta['tache_id']);
        } elseif (! empty($meta['instance_id']) && ! empty($meta['etape_ordre'])) {
            $tache = WorkflowTache::query()->where('workflow_instance_id', (int) $meta['instance_id'])
                ->where('etape_ordre', (int) $meta['etape_ordre'])->latest('id')->first();
        }
        if ($tache) {
            return match ($tache->statut) {
                'terminee' => NotificationNature::ACTION_TRAITEE,
                'annulee' => NotificationNature::ACTION_ANNULEE,
                default => NotificationNature::ACTION_EN_ATTENTE,
            };
        }
        if (! empty($meta['instance_id'])) {
            $instance = WorkflowInstance::query()->find((int) $meta['instance_id']);
            if (! $instance) {
                return NotificationNature::ACTION_ANNULEE;
            }
            if ($instance->statut !== 'en_cours') {
                return NotificationNature::ACTION_TRAITEE;
            }
            if (! empty($meta['etape_ordre'])) {
                $etape = $instance->etapes()->where('ordre', (int) $meta['etape_ordre'])->first();

                return $etape && $etape->statut === 'en_attente' ? NotificationNature::ACTION_EN_ATTENTE : NotificationNature::ACTION_TRAITEE;
            }
        }

        return null;
    }

    /**
     * Actions adressées hors circuit sur un dossier (ex. « paiement à préparer ») : traitées dès qu'un circuit
     * démarre sur ce dossier. Les actions d'étape sont closes par Mes Tâches.
     */
    public static function cloreActionsHorsCircuit(Model $objet): int
    {
        $ids = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('ressource_type', $objet->getMorphClass())->where('ressource_id', $objet->getKey())
            ->where('action_statut', NotificationNature::ACTION_EN_ATTENTE)
            ->where('type_code', 'not like', 'workflow.%')
            ->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        return \Illuminate\Support\Facades\DB::table('notifications')->whereIn('id', $ids)->update([
            'action_statut' => NotificationNature::ACTION_TRAITEE,
            'action_traitee_le' => now(),
            'read_at' => \Illuminate\Support\Facades\DB::raw('COALESCE(read_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function ressource(DatabaseNotification $notification): ?Model
    {
        $classe = $notification->ressource_type ? (Relation::getMorphedModel($notification->ressource_type) ?? $notification->ressource_type) : null;
        if ($classe && class_exists($classe) && $notification->ressource_id) {
            return $classe::query()->find($notification->ressource_id);
        }
        $trouvee = self::ressourceDepuisUrl(data_get($notification->data, 'url'));

        return $trouvee ? $trouvee[0]::query()->find($trouvee[1]) : null;
    }

    /**
     * Dossier désigné par un lien interne (notifications antérieures à la colonne « ressource »).
     *
     * @return array{0: class-string<Model>, 1: int}|null
     */
    public static function ressourceDepuisUrl(?string $url): ?array
    {
        $chemin = (string) parse_url((string) $url, PHP_URL_PATH);

        return match (true) {
            (bool) preg_match('#^/depense/([a-z_]+)/(\d+)#', $chemin, $m) && isset(GedAttachmentService::MODULES[$m[1]]) => [GedAttachmentService::MODULES[$m[1]], (int) $m[2]],
            (bool) preg_match('#^/besoins/(\d+)#', $chemin, $m) => [ExpressionBesoin::class, (int) $m[1]],
            (bool) preg_match('#^/engagements/(\d+)#', $chemin, $m) => [Engagement::class, (int) $m[1]],
            (bool) preg_match('#^/mes-taches/(\d+)#', $chemin, $m) => self::objetDeTache((int) $m[1]),
            default => null,
        };
    }

    /** @return array{0: class-string<Model>, 1: int}|null */
    private static function objetDeTache(int $tacheId): ?array
    {
        $tache = WorkflowTache::query()->find($tacheId);
        $classe = $tache?->objet_type ? (Relation::getMorphedModel($tache->objet_type) ?? $tache->objet_type) : null;

        return $classe && $tache->objet_id ? [$classe, (int) $tache->objet_id] : null;
    }

    private function estInterne(string $url): bool
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//');
    }
}
