<?php

namespace App\Services\Institution;

use App\Models\Structure;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Support\Collection;

/** Préparation des exports institutionnels (annuaire, cadre organique). Phase A5. */
class InstitutionExportService
{
    /** @var array<string, string> */
    public const TYPES_LIBELLES = [
        'presidence' => 'Presidence',
        'vice_presidence' => 'Vice-presidence',
        'secretariat_general' => 'Secretariat General',
        'commissariat' => 'Commissariat',
        'departement' => 'Departement',
        'direction' => 'Direction',
        'service' => 'Service',
        'cellule' => 'Cellule',
    ];

    public function __construct(private readonly AccessScopeService $scopes)
    {
    }

    /** @return Collection<int, array<string, mixed>> */
    public function annuairePourExport(User $user, ?string $type = null, ?string $q = null): Collection
    {
        $query = Structure::query()
            ->with(['parent', 'responsable', 'structureOfficielle'])
            ->whereNull('deleted_at')
            ->orderBy('niveau')
            ->orderBy('ordre')
            ->orderBy('code');

        if (! $this->scopes->estGlobal($user)) {
            $this->scopes->filtrerReferentielStructures($query, $user);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($q) {
            $query->where(fn ($s) => $s
                ->where('libelle', 'like', "%{$q}%")
                ->orWhere('code', 'like', "%{$q}%")
                ->orWhere('sigle', 'like', "%{$q}%"));
        }

        return $query->get()->map(fn (Structure $structure) => $this->ligneAnnuaire($structure));
    }

    /** @return array<string, mixed> */
    private function ligneAnnuaire(Structure $structure): array
    {
        return [
            'code' => $structure->code,
            'libelle' => $structure->libelle,
            'sigle' => $structure->sigle ?? '—',
            'type' => self::TYPES_LIBELLES[$structure->type] ?? $structure->type,
            'type_code' => $structure->type,
            'parent_code' => $structure->parent?->code ?? '—',
            'parent_libelle' => $structure->parent?->libelle ?? '—',
            'departement' => $structure->departement()?->libelle ?? '—',
            'departement_code' => $structure->departement()?->code ?? '—',
            'direction' => $structure->direction()?->libelle ?? '—',
            'direction_code' => $structure->direction()?->code ?? '—',
            'service' => $structure->service()?->libelle ?? '—',
            'service_code' => $structure->service()?->code ?? '—',
            'responsable' => $structure->responsable?->name ?? '—',
            'responsable_email' => $structure->responsable?->email ?? '—',
            'structure_officielle' => $structure->structureOfficielle?->code ?? '—',
            'niveau' => $structure->niveau,
            'actif' => $structure->actif ? 'Oui' : 'Non',
        ];
    }
}
