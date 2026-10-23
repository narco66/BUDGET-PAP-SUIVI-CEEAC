<?php

namespace App\Services\Interop;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/** Charge et valide la spécification OpenAPI (I-P3). */
class OpenApiService
{
    public function cheminSpec(): string
    {
        return resource_path('openapi/v1.json');
    }

    /** @return array<string, mixed> */
    public function specification(): array
    {
        $chemin = $this->cheminSpec();
        if (! File::exists($chemin)) {
            throw new \RuntimeException('Spécification OpenAPI introuvable.');
        }

        $spec = json_decode(File::get($chemin), true, 512, JSON_THROW_ON_ERROR);

        $spec['servers'] = [[
            'url' => url('/api/v1'),
            'description' => 'API REST CEEAC v1',
        ]];

        return $spec;
    }

    /** @return array<int, string> */
    public function routesApiEnregistrees(): array
    {
        return collect(Route::getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1'))
            ->map(fn ($r) => strtoupper(implode('|', $r->methods())).' /'.$r->uri())
            ->sort()
            ->values()
            ->all();
    }

    public function estValide(): bool
    {
        $spec = $this->specification();

        return isset($spec['openapi'], $spec['info'], $spec['paths'])
            && version_compare((string) $spec['openapi'], '3.0.0', '>=');
    }

    /** @return array<int, array{methode: string, chemin: string, operationId: string}> */
    public function inventaireEndpoints(): array
    {
        $spec = $this->specification();
        $items = [];

        foreach ($spec['paths'] as $chemin => $methodes) {
            foreach ($methodes as $methode => $operation) {
                if (! in_array(strtolower($methode), ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }
                $items[] = [
                    'methode' => strtoupper($methode),
                    'chemin' => '/api/v1'.$chemin,
                    'operationId' => $operation['operationId'] ?? '',
                    'tags' => implode(', ', $operation['tags'] ?? []),
                    'summary' => $operation['summary'] ?? '',
                ];
            }
        }

        return $items;
    }
}
