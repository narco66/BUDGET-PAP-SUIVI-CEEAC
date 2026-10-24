<?php

namespace App\Services\Navigation;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** Construit le menu sidebar depuis config/menu.php (P2-7). */
class MenuService
{
    private int $groupeSequence = 0;

    public function __construct(private readonly Request $request) {}

    /** @return list<array<string, mixed>> */
    public function build(?User $user = null): array
    {
        $this->groupeSequence = 0;
        $user ??= auth()->user();
        if ($user === null) {
            return [];
        }

        $brut = [];
        foreach (config('menu.items', []) as $entry) {
            if (! $this->visiblePour($entry, $user)) {
                continue;
            }
            if (($entry['type'] ?? 'link') === 'section') {
                $brut[] = ['type' => 'section', 'label' => $entry['label']];
                continue;
            }

            if (($entry['type'] ?? 'link') === 'group') {
                $groupe = $this->resoudreGroupe($entry, $user);
                if ($groupe !== null) {
                    $brut[] = $groupe;
                }
                continue;
            }

            $brut[] = $this->resoudreLien($entry);
        }

        return $this->retirerSectionsVides($brut);
    }

    /**
     * Menu sidebar enrichi pour le SPA : chemin frontend si le parcours est migré,
     * sinon lien vers l'interface Blade (coexistence).
     *
     * @return list<array<string, mixed>>
     */
    public function buildSpa(?User $user = null): array
    {
        return $this->annoterSpa($this->build($user));
    }

    /** @param  list<array<string, mixed>>  $items */
    private function annoterSpa(array $items): array
    {
        $carte = config('spa.routes', []);
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        return array_map(function (array $item) use ($carte, $legacy) {
            if (($item['type'] ?? '') === 'group') {
                $item['children'] = $this->annoterSpa($item['children'] ?? []);

                return $item;
            }

            if (($item['type'] ?? '') !== 'link') {
                return $item;
            }

            $route = $item['route'] ?? null;
            $params = $item['route_params'] ?? [];
            $path = null;
            if (is_string($route)) {
                $suffixe = is_array($params) && $params !== [] ? (string) array_values($params)[0] : '';
                if ($suffixe !== '' && isset($carte[$route.'.'.$suffixe])) {
                    $path = $carte[$route.'.'.$suffixe];
                } else {
                    $path = $carte[$route] ?? null;
                }
            }
            $item['path'] = $path;
            $item['migre'] = $path !== null;
            $item['href_legacy'] = $item['href'] ?? ($legacy.'/');

            return $item;
        }, $items);
    }

    private function visiblePour(array $entry, User $user): bool
    {
        if (isset($entry['permission'])) {
            return $user->can($entry['permission']);
        }
        if (isset($entry['permissions'])) {
            foreach ($entry['permissions'] as $perm) {
                if ($user->can($perm)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function resoudreLien(array $entry): array
    {
        $params = $entry['route_params'] ?? [];
        $href = Route::has($entry['route'])
            ? route($entry['route'], $params)
            : '#';

        return [
            'type' => 'link',
            'label' => $entry['label'],
            'icon' => $entry['icon'] ?? 'bi-circle',
            'route' => $entry['route'] ?? null,
            'route_params' => $params,
            'href' => $href,
            'active' => $this->estActif($entry),
        ];
    }

    /** @return array<string, mixed>|null */
    private function resoudreGroupe(array $entry, User $user): ?array
    {
        $enfants = [];
        foreach ($entry['children'] ?? [] as $child) {
            if (! $this->visiblePour($child, $user)) {
                continue;
            }

            if (($child['type'] ?? 'link') === 'group') {
                $groupe = $this->resoudreGroupe($child, $user);
                if ($groupe !== null) {
                    $enfants[] = $groupe;
                }
                continue;
            }

            if (($child['type'] ?? 'link') === 'link') {
                $enfants[] = $this->resoudreLien($child);
            }
        }

        if ($enfants === []) {
            return null;
        }

        return [
            'type' => 'group',
            'label' => $entry['label'],
            'icon' => $entry['icon'] ?? 'bi-folder',
            'id' => 'sidebar-group-'.(++$this->groupeSequence),
            'active' => $this->contientActif($enfants),
            'children' => $enfants,
        ];
    }

    /** @param  list<array<string, mixed>>  $items */
    private function contientActif(array $items): bool
    {
        foreach ($items as $item) {
            if (($item['active'] ?? false) === true) {
                return true;
            }

            if (($item['type'] ?? '') === 'group' && $this->contientActif($item['children'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    private function estActif(array $entry): bool
    {
        if (isset($entry['active_rules'])) {
            return $this->evaluerReglesActives($entry['active_rules']);
        }

        $active = $entry['active'] ?? $entry['route'];

        return is_array($active)
            ? $this->request->routeIs(...$active)
            : $this->request->routeIs($active);
    }

    /** @param  array<string, mixed>  $rules */
    private function evaluerReglesActives(array $rules): bool
    {
        $routes = $rules['routes'] ?? [];
        if ($routes === [] || ! $this->request->routeIs(...$routes)) {
            return false;
        }

        foreach ($rules['exclude'] ?? [] as $exclu) {
            if ($this->request->routeIs($exclu)) {
                return false;
            }
        }

        foreach ($rules['exclude_routes'] ?? [] as $prefix) {
            if ($this->request->routeIs($prefix)) {
                return false;
            }
        }

        if (isset($rules['segment'])) {
            foreach ($rules['segment'] as $index => $value) {
                if ($this->request->segment((int) $index) !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param  list<array<string, mixed>>  $items */
    private function retirerSectionsVides(array $items): array
    {
        $resultat = [];
        $count = count($items);

        for ($i = 0; $i < $count; $i++) {
            if (($items[$i]['type'] ?? '') !== 'section') {
                $resultat[] = $items[$i];
                continue;
            }

            $aDesLiens = false;
            for ($j = $i + 1; $j < $count; $j++) {
                if (($items[$j]['type'] ?? '') === 'section') {
                    break;
                }
                $aDesLiens = in_array(($items[$j]['type'] ?? ''), ['link', 'group'], true);
                break;
            }

            if ($aDesLiens) {
                $resultat[] = $items[$i];
            }
        }

        return $resultat;
    }
}
