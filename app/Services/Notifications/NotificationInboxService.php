<?php

namespace App\Services\Notifications;

use App\Models\NotificationType;
use App\Models\User;
use App\Support\Horodatage;
use App\Support\NotificationCriticite;
use App\Support\NotificationNature;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

/**
 * Boîte de réception de l'utilisateur : uniquement ses propres notifications (pas d'accès indirect, § 15).
 * Recherche, filtres et pagination côté serveur (§ 8.3), portables PostgreSQL / SQLite.
 */
class NotificationInboxService
{
    public const FILTRES = ['module', 'type', 'lu', 'archive', 'q', 'nature', 'priorite', 'action', 'du', 'au'];

    public function __construct(private readonly NotificationActionResolver $resolver) {}

    /** @return array<string, int|float|null> */
    public function stats(User $user): array
    {
        $actives = fn () => $user->notifications()->whereNull('archive_at');
        $total = $actives()->count();
        $lues = $actives()->whereNotNull('read_at')->count();

        return [
            'non_lues' => $total - $lues,
            'aujourdhui' => $actives()->where('created_at', '>=', now()->startOfDay())->count(),
            'critiques' => $actives()->whereNull('read_at')
                ->whereIn('criticite', [NotificationCriticite::CRITIQUE, NotificationCriticite::URGENCE])->count(),
            'actions_en_attente' => $actives()->where('action_statut', NotificationNature::ACTION_EN_ATTENTE)->count(),
            'total' => $total,
            'taux_lecture' => $total > 0 ? round($lues / $total * 100, 1) : null,
        ];
    }

    /** Compteur de la cloche : non lues et non archivées. */
    public function nonLues(User $user): int
    {
        return $user->unreadNotifications()->whereNull('archive_at')->count();
    }

    public function requete(User $user, Request $request): MorphMany
    {
        $query = $user->notifications()->latest();

        if ($request->boolean('unread') || $request->string('lu')->toString() === '0') {
            $query->whereNull('read_at');
        } elseif ($request->string('lu')->toString() === '1') {
            $query->whereNotNull('read_at');
        }
        // Colonnes dédiées (renseignées par le dispatcher et la rétro-alimentation) : pas d'accès JSON fragile.
        $query->when($request->filled('module'), fn ($q) => $q->where('module', $request->string('module')->toString()))
            ->when($request->filled('type'), fn ($q) => $q->where('type_code', $request->string('type')->toString()))
            ->when($request->filled('nature'), fn ($q) => $q->where('nature', $request->string('nature')->toString()))
            ->when($request->filled('priorite'), fn ($q) => $q->whereIn('criticite', NotificationCriticite::deLaPriorite($request->string('priorite')->toString())))
            ->when($request->filled('action'), fn ($q) => $q->where('action_statut', $request->string('action')->toString()))
            ->when($request->filled('du'), fn ($q) => $q->where('created_at', '>=', $this->debutJour($request->string('du')->toString())))
            ->when($request->filled('au'), fn ($q) => $q->where('created_at', '<', $this->debutJour($request->string('au')->toString())->addDay()));

        if ($request->string('archive')->toString() === '1') {
            $query->whereNotNull('archive_at');
        } else {
            $query->whereNull('archive_at');
        }

        if ($request->filled('q')) {
            $terme = '%'.mb_strtolower(trim($request->string('q')->toString())).'%';
            $pg = DB::getDriverName() === 'pgsql';
            $champ = fn (string $c) => $pg ? "LOWER(data->>'{$c}')" : "LOWER(json_extract(data, '$.{$c}'))";
            $query->where(fn ($q) => $q->whereRaw($champ('titre').' LIKE ?', [$terme])
                ->orWhereRaw($champ('message').' LIKE ?', [$terme])
                ->orWhereRaw('LOWER(reference) LIKE ?', [$terme]));
        }

        return $query;
    }

    /** @return array<string, mixed> */
    public function serialiser(DatabaseNotification $n): array
    {
        $criticite = $n->criticite ?? data_get($n->data, 'criticite');

        return [
            'id' => $n->id,
            'type_code' => $n->type_code ?? data_get($n->data, 'type_code'),
            'module' => $n->module ?? data_get($n->data, 'module'),
            'criticite' => $criticite,
            'priorite' => NotificationCriticite::priorite($criticite),
            'nature' => $n->nature ?? NotificationNature::INFORMATION,
            'titre' => data_get($n->data, 'titre'),
            'message' => data_get($n->data, 'message'),
            'url' => data_get($n->data, 'url'),
            'lien' => route('notifications.ouvrir', $n->id, false),
            'ressource' => $n->ressource_type ? [
                'type' => class_basename($n->ressource_type),
                'id' => $n->ressource_id,
                'reference' => $n->reference,
            ] : null,
            'action' => $n->action_statut ? [
                'statut' => $n->action_statut,
                'libelle' => NotificationNature::ETATS_ACTION[$n->action_statut] ?? $n->action_statut,
                'disponible' => $n->action_statut === NotificationNature::ACTION_EN_ATTENTE,
                'traitee_le' => $n->action_traitee_le ? Horodatage::utc($n->action_traitee_le) : null,
                'traitee_par' => data_get($n->data, 'meta.traitee_par'),
            ] : null,
            'lu' => $n->read_at !== null,
            'lu_le' => $n->read_at?->toIso8601String(),
            'archive' => $n->archive_at !== null,
            'cree_le' => $n->created_at?->toIso8601String(),
            'cree_le_local' => Horodatage::local($n->created_at, 'd/m/Y H:i'),
        ];
    }

    public function trouver(User $user, string $id): DatabaseNotification
    {
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $this->resolver->actualiserEtat($notification);

        return $notification;
    }

    public function marquerLue(User $user, string $id): DatabaseNotification
    {
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return $notification->fresh();
    }

    /** Lu / non lu : indépendant de l'état d'action (RN-05). */
    public function marquerNonLue(User $user, string $id): DatabaseNotification
    {
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsUnread();

        return $notification->fresh();
    }

    /** Uniquement les notifications du destinataire (NOT-016). */
    public function toutLu(User $user): int
    {
        return $user->unreadNotifications()->whereNull('archive_at')->update(['read_at' => now()]);
    }

    public function archiver(User $user, string $id): DatabaseNotification
    {
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->forceFill(['archive_at' => now()])->save();

        return $notification->fresh();
    }

    /** @return array<string, mixed> */
    public function filtres(): array
    {
        return [
            'modules' => NotificationType::query()->where('actif', true)->distinct()->orderBy('module')->pluck('module')->values()->all(),
            'types' => NotificationType::query()->where('actif', true)->orderBy('module')->orderBy('code')->get(['code', 'libelle', 'module']),
            'natures' => NotificationNature::LIBELLES,
            'priorites' => NotificationCriticite::PRIORITES,
            'actions' => NotificationNature::ETATS_ACTION,
        ];
    }

    private function debutJour(string $date): \Carbon\CarbonImmutable
    {
        // Dates saisies dans le fuseau institutionnel, comparées à des instants UTC.
        return \Carbon\CarbonImmutable::parse($date, Horodatage::fuseau())->startOfDay()->utc();
    }
}
