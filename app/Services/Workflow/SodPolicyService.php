<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceEtape;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Séparation des fonctions (SoD) : validations workflow et cumul de rôles incompatibles.
 */
class SodPolicyService
{
    /** @param  array<int, string>  $roles */
    public function assertRolesCompatibles(array $roles): void
    {
        if (! config('workflow.sod.incompatible_roles_enabled', true)) {
            return;
        }

        $set = array_values(array_unique($roles));

        foreach (config('workflow.sod.incompatible_roles', []) as $pair) {
            if (! is_array($pair) || count($pair) !== 2) {
                continue;
            }
            [$a, $b] = $pair;
            if (in_array($a, $set, true) && in_array($b, $set, true)) {
                $message = "Séparation des fonctions : les rôles « {$a} » et « {$b} » ne peuvent pas être cumulés sur un même compte.";

                throw ValidationException::withMessages(['roles' => $message]);
            }
        }
    }

    public function assertValidationAllowed(WorkflowInstance $instance, User $user, WorkflowInstanceEtape $etape): void
    {
        if (! config('workflow.sod.enabled', true)) {
            return;
        }

        if ($user->estAdministrateur()) {
            return;
        }

        $this->assertPasDeValidationConsecutive($instance, $user, $etape);
        $this->assertCreateurNeValidePas($instance, $user, $etape);
    }

    public function resolveInitiateur(WorkflowInstance $instance): ?int
    {
        $objet = $instance->relationLoaded('objet') ? $instance->objet : $instance->objet()->first();

        if ($objet instanceof Model) {
            foreach (['cree_par', 'created_by', 'demandeur_id'] as $attr) {
                $val = $objet->getAttribute($attr);
                if ($val) {
                    return (int) $val;
                }
            }
        }

        return $instance->demarre_par ? (int) $instance->demarre_par : null;
    }

    private function assertPasDeValidationConsecutive(WorkflowInstance $instance, User $user, WorkflowInstanceEtape $etape): void
    {
        if ($etape->ordre <= 1) {
            return;
        }

        $precedente = $instance->etapes()
            ->where('ordre', $etape->ordre - 1)
            ->where('statut', 'valide')
            ->latest('decision_le')
            ->first();

        if ($precedente && (int) $precedente->acteur_id === (int) $user->id) {
            $this->journaliserViolation($instance, $etape, 'Même acteur que l\'étape précédente (séparation des fonctions).');
            throw new RuntimeException('Séparation des fonctions : vous avez déjà validé l\'étape précédente.');
        }
    }

    private function assertCreateurNeValidePas(WorkflowInstance $instance, User $user, WorkflowInstanceEtape $etape): void
    {
        if (! config('workflow.sod.creator_cannot_validate', true)) {
            return;
        }

        $initiateurId = $this->resolveInitiateur($instance);
        if ($initiateurId && (int) $initiateurId === (int) $user->id) {
            $this->journaliserViolation($instance, $etape, 'Le créateur du dossier ne peut pas valider une étape du circuit.');
            throw new RuntimeException('Séparation des fonctions : vous ne pouvez pas valider un dossier dont vous êtes l\'initiateur.');
        }
    }

    private function journaliserViolation(WorkflowInstance $instance, WorkflowInstanceEtape $etape, string $motif): void
    {
        AuditLogger::enregistrer('sod_violation', $instance, null, [
            'workflow' => $instance->workflow?->code,
            'etape' => $etape->ordre,
            'role_etape' => $etape->role,
            'motif' => $motif,
        ]);
    }
}
