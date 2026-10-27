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

    /** @return list<string> rôles dont le titulaire se déduit de l'organigramme (niveau ou structure fixe) */
    public static function rolesOrganiques(): array
    {
        return array_merge(array_keys(self::ROLE_TYPE_STRUCTURE), array_keys(self::ROLE_CODE_STRUCTURE));
    }

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

    /** @var array<string, ?Structure> structures validatrices déjà résolues pendant la requête (origine|rôle) */
    private array $validateurs = [];

    /** Version de l'organigramme : incrémentée à chaque modification de structure, elle invalide les mémoires. */
    private static int $versionOrganigramme = 0;

    private int $versionMemo = 0;

    public static function organigrammeModifie(): void
    {
        self::$versionOrganigramme++;
    }

    public function structureValidateur(?int $structureOrigineId, string $roleRequis): ?Structure
    {
        if ($this->versionMemo !== self::$versionOrganigramme) {
            $this->validateurs = [];
            $this->versionMemo = self::$versionOrganigramme;
        }
        $cle = ($structureOrigineId ?? 0).'|'.$roleRequis;
        if (! array_key_exists($cle, $this->validateurs)) {
            $this->validateurs[$cle] = $this->resoudreValidateur($structureOrigineId, $roleRequis);
        }

        return $this->validateurs[$cle];
    }

    private function resoudreValidateur(?int $structureOrigineId, string $roleRequis): ?Structure
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
