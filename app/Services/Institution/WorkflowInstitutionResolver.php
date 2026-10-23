<?php

namespace App\Services\Institution;

use App\Models\Structure;
use App\Models\User;

/** Résout les habilitations workflow via responsables, délégations et intérims (phase A7). */
class WorkflowInstitutionResolver
{
    /** @var array<string, string> */
    private const ROLE_TYPE_STRUCTURE = [
        'chef_service' => 'service',
        'directeur' => 'direction',
        'commissaire' => 'departement',
    ];

    /** @var array<string, string> */
    private const ROLE_CODE_STRUCTURE = [
        'secretaire_general' => 'SG',
        'president' => 'PRES',
    ];

    public function __construct(
        private readonly InstitutionGouvernanceService $gouvernance,
    ) {}

    public function peutAgirSurEtape(User $user, ?int $structureOrigineId, string $roleRequis): bool
    {
        $structureCible = $this->structureValidateur($structureOrigineId, $roleRequis);
        if (! $structureCible?->responsable_user_id) {
            return false;
        }

        $responsable = User::find($structureCible->responsable_user_id);
        if (! $responsable) {
            return false;
        }

        return $this->gouvernance->utilisateurSubstitueResponsable($user, $responsable, $structureCible->id);
    }

    public function structureValidateur(?int $structureOrigineId, string $roleRequis): ?Structure
    {
        if (isset(self::ROLE_CODE_STRUCTURE[$roleRequis])) {
            return Structure::query()
                ->where('code', self::ROLE_CODE_STRUCTURE[$roleRequis])
                ->where('actif', true)
                ->first();
        }

        $type = self::ROLE_TYPE_STRUCTURE[$roleRequis] ?? null;
        if (! $type || ! $structureOrigineId) {
            return null;
        }

        $origine = Structure::find($structureOrigineId);
        if (! $origine) {
            return null;
        }

        return $this->structureAuNiveau($origine, $type);
    }

    private function structureAuNiveau(Structure $structure, string $type): ?Structure
    {
        if ($structure->type === $type) {
            return $structure;
        }

        foreach ($structure->ancetres() as $ancetre) {
            if ($ancetre->type === $type) {
                return $ancetre;
            }
        }

        return null;
    }
}
