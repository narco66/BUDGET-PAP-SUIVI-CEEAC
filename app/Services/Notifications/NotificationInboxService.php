<?php

namespace App\Services\Notifications;

use App\Models\NotificationType;
use App\Models\User;
use App\Support\NotificationCriticite;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationInboxService
{
    /** @return array{non_lues: int, aujourdhui: int, critiques: int} */
    public function stats(User $user): array
    {
        return [
            'non_lues' => $user->unreadNotifications()->whereNull('archive_at')->count(),
            'aujourdhui' => $user->notifications()->whereNull('archive_at')->whereDate('created_at', today())->count(),
            'critiques' => $user->notifications()
                ->whereNull('archive_at')
                ->whereIn('criticite', [NotificationCriticite::CRITIQUE, NotificationCriticite::URGENCE])
                ->count(),
        ];
    }

    public function requete(User $user, Request $request): MorphMany
    {
        $query = $user->notifications()->latest();

        if ($request->boolean('unread') || $request->string('lu')->toString() === '0') {
            $query->whereNull('read_at');
        } elseif ($request->string('lu')->toString() === '1') {
            $query->whereNotNull('read_at');
        }

        if ($request->filled('module')) {
            $module = $request->string('module')->toString();
            $query->where(function ($q) use ($module) {
                $q->where('module', $module)
                    ->orWhere('data->module', $module);
            });
        }

        if ($request->filled('type')) {
            $type = $request->string('type')->toString();
            $query->where(function ($q) use ($type) {
                $q->where('type_code', $type)
                    ->orWhere('data->type', $type)
                    ->orWhere('data->type_code', $type);
            });
        }

        if ($request->string('archive')->toString() === '1') {
            $query->whereNotNull('archive_at');
        } else {
            $query->whereNull('archive_at');
        }

        if ($request->filled('q')) {
            $terme = '%'.$request->string('q')->toString().'%';
            $query->where(function ($q) use ($terme) {
                $q->where('data->titre', 'like', $terme)
                    ->orWhere('data->message', 'like', $terme);
            });
        }

        return $query;
    }

    /** @return array<string, mixed> */
    public function serialiser(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id,
            'type_code' => $n->type_code ?? data_get($n->data, 'type_code'),
            'module' => $n->module ?? data_get($n->data, 'module'),
            'criticite' => $n->criticite ?? data_get($n->data, 'criticite'),
            'titre' => data_get($n->data, 'titre'),
            'message' => data_get($n->data, 'message'),
            'url' => data_get($n->data, 'url'),
            'lu' => $n->read_at !== null,
            'lu_le' => $n->read_at?->toIso8601String(),
            'archive' => $n->archive_at !== null,
            'cree_le' => $n->created_at?->toIso8601String(),
        ];
    }

    public function marquerLue(User $user, string $id): DatabaseNotification
    {
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return $notification->fresh();
    }

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
        ];
    }
}
