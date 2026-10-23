<?php

namespace App\Services\Institution;

use App\Models\Structure;

/** Règles de cohérence hiérarchique du référentiel `structures` (phase A1). */
class StructureHierarchieService
{
    /** @var array<string, list<string|null>> */
    private const PARENTS_AUTORISES = [
        'presidence' => [null],
        'vice_presidence' => [null],
        'secretariat_general' => ['presidence'],
        'departement' => ['presidence', 'vice_presidence', 'secretariat_general'],
        'direction' => ['departement', 'direction', 'cellule', 'secretariat_general', 'presidence', 'vice_presidence'],
        'service' => ['direction', 'cellule'],
        'cellule' => ['departement', 'direction', 'cellule', 'presidence', 'vice_presidence'],
        'commissariat' => ['departement', 'presidence', 'vice_presidence'],
    ];

    public function valider(string $type, ?int $parentId, ?int $structureId = null): ?string
    {
        $autorises = self::PARENTS_AUTORISES[$type] ?? null;

        if ($autorises === null) {
            return 'Type de structure non reconnu.';
        }

        if ($parentId === null) {
            return in_array(null, $autorises, true)
                ? null
                : 'Une structure parente est obligatoire pour ce type.';
        }

        if (! in_array(null, $autorises, true)) {
            // parent requis — ok si parentId fourni
        }

        $parent = Structure::find($parentId);

        if (! $parent) {
            return 'La structure parente sélectionnée est introuvable.';
        }

        if ($structureId && $this->estDescendant($parentId, $structureId)) {
            return 'Une structure ne peut pas être rattachée à l\'une de ses descendants.';
        }

        if (! in_array($parent->type, $autorises, true)) {
            return "Un(e) {$type} ne peut pas être rattaché(e) à un parent de type « {$parent->type} ».";
        }

        if ($type === 'direction' && ! $this->directionAncetreDepartement($parent)) {
            return 'Une direction doit relever d\'un département institutionnel (directement ou via la hiérarchie).';
        }

        return null;
    }

    /** @return list<string> */
    public function listerAnomalies(): array
    {
        $anomalies = [];

        Structure::query()->whereNull('deleted_at')->orderBy('code')->each(function (Structure $s) use (&$anomalies) {
            $msg = $this->valider($s->type, $s->parent_id, $s->id);
            if ($msg) {
                $anomalies[] = "{$s->code} ({$s->type}) — {$msg}";
            }
        });

        return $anomalies;
    }

    /** @return list<string> */
    public function departementsSansDirections(): array
    {
        return Structure::query()
            ->where('type', 'departement')
            ->whereNull('deleted_at')
            ->where('actif', true)
            ->whereNotIn('code', ['DPRES', 'DVP', 'DEPT-PLAN'])
            ->orderBy('code')
            ->get()
            ->filter(fn (Structure $d) => ! $this->departementPossedeDirection($d))
            ->map(fn (Structure $d) => "{$d->code} — {$d->libelle}")
            ->values()
            ->all();
    }

    private function departementPossedeDirection(Structure $departement): bool
    {
        return Structure::query()
            ->whereNull('deleted_at')
            ->where('actif', true)
            ->where('type', 'direction')
            ->where(function ($q) use ($departement) {
                $q->where('parent_id', $departement->id)
                    ->orWhereIn('parent_id', function ($sub) use ($departement) {
                        $sub->select('id')->from('structures')
                            ->where('parent_id', $departement->id)
                            ->whereNull('deleted_at');
                    });
            })
            ->exists();
    }

    private function directionAncetreDepartement(Structure $parent): bool
    {
        if ($parent->type === 'departement') {
            return true;
        }

        if (in_array($parent->type, ['secretariat_general', 'presidence', 'vice_presidence'], true)) {
            return true;
        }

        $courant = $parent;

        for ($i = 0; $i < 8 && $courant; $i++) {
            if ($courant->type === 'departement') {
                return true;
            }

            $courant = $courant->parent;
        }

        return false;
    }

    private function estDescendant(int $candidateParentId, int $structureId): bool
    {
        $courant = Structure::find($candidateParentId);

        while ($courant) {
            if ((int) $courant->id === $structureId) {
                return true;
            }

            $courant = $courant->parent;
        }

        return false;
    }
}
