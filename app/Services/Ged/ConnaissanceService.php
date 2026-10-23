<?php

namespace App\Services\Ged;

use App\Models\Ged\Connaissance;
use App\Models\Ged\ConnaissanceVersion;
use App\Models\User;
use Illuminate\Support\Str;

class ConnaissanceService
{
    /** @param array<string, mixed> $data */
    public function creer(array $data, User $auteur): Connaissance
    {
        $data['reference'] ??= $this->reference($data['type']);
        $data['slug'] = $this->slugUnique($data['titre']);
        $data['auteur_id'] = $auteur->id;
        $data['statut'] ??= 'brouillon';
        $data['version'] = 1;

        $item = Connaissance::create($data);
        $this->archiverVersion($item, $auteur->id);

        return $item;
    }

    /** @param array<string, mixed> $data */
    public function mettreAJour(Connaissance $item, array $data, User $auteur): Connaissance
    {
        if (isset($data['titre']) && $data['titre'] !== $item->titre) {
            $data['slug'] = $this->slugUnique($data['titre'], $item->id);
        }

        if (isset($data['contenu']) && $data['contenu'] !== $item->contenu) {
            $data['version'] = $item->version + 1;
        }

        $item->update($data);

        if (isset($data['contenu'])) {
            $this->archiverVersion($item->fresh(), $auteur->id);
        }

        return $item->fresh();
    }

    public function publier(Connaissance $item, User $user): Connaissance
    {
        $item->update([
            'statut' => 'publie',
            'publie_par_id' => $user->id,
            'publie_le' => now(),
        ]);

        return $item->fresh();
    }

    public function archiver(Connaissance $item): Connaissance
    {
        $item->update(['statut' => 'archive']);

        return $item->fresh();
    }

    private function reference(string $type): string
    {
        $prefix = $type === 'procedure' ? 'CON-PROC' : 'CON-WIKI';
        $seq = Connaissance::withTrashed()->where('type', $type)->count() + 1;

        return sprintf('%s-%03d', $prefix, $seq);
    }

    private function slugUnique(string $titre, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($titre, 80, ''));
        $slug = $base;
        $i = 1;

        while (Connaissance::withTrashed()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function archiverVersion(Connaissance $item, int $auteurId): void
    {
        ConnaissanceVersion::updateOrCreate(
            ['connaissance_id' => $item->id, 'version' => $item->version],
            ['contenu' => $item->contenu, 'auteur_id' => $auteurId, 'created_at' => now()]
        );
    }
}
