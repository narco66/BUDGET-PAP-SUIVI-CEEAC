<?php

namespace App\Services\Workflow;

use App\Models\AuditWorkflow;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/** Journal MOA workflow (P1) — complète la piste d'audit globale. */
class WorkflowAuditService
{
    /** Acte enregistré selon le libellé de l'étape validée (JNL-001, § 5.3 actes sensibles). */
    private const ACTES = ['visa' => 'VISA', 'certification' => 'CERTIFICATION', 'signature' => 'SIGNATURE',
        'ordonnancement' => 'SIGNATURE', 'autorisation' => 'SIGNATURE', 'approbation' => 'APPROBATION'];

    public function enregistrer(
        string $action,
        WorkflowInstance $instance,
        ?User $acteur = null,
        ?int $etapeOrdre = null,
        ?string $observation = null,
        ?string $categorieRejet = null,
        ?array $meta = null,
    ): AuditWorkflow {
        $objet = $instance->relationLoaded('objet') ? $instance->objet : $instance->objet()->first();
        $module = TacheWorkflowService::module($instance->workflow?->code);

        AuditLogger::evenement($action, $objet, [
            'code' => $module.'.'.$this->acte($action, $meta['etape'] ?? null),
            'module' => $module,
            'motif' => $observation,
            'gravite' => in_array($action, ['workflow_valide', 'workflow_rejete', 'workflow_renvoye'], true) ? 'modere' : null,
            'workflow_instance_id' => $instance->id,
            'workflow_tache_id' => $meta['tache_id'] ?? null,
            'pour_compte_de' => $meta['pour_compte_de'] ?? null,
            'apres' => array_filter([
                'workflow' => $instance->workflow?->code,
                'instance_id' => $instance->id,
                'etape' => $etapeOrdre,
                'etape_libelle' => $meta['etape'] ?? null,
                'role_competent' => $meta['role'] ?? null,
                'statut_circuit' => $instance->statut,
                'observation' => $observation,
                'categorie_rejet' => $categorieRejet,
                ...$this->detailsMetier($objet),
            ], fn ($v) => $v !== null),
        ]);

        return AuditWorkflow::create([
            'workflow_instance_id' => $instance->id,
            'workflow_code' => $instance->workflow?->code,
            'objet_type' => $objet ? $objet::class : null,
            'objet_id' => $objet?->getKey(),
            'action' => $action,
            'etape_ordre' => $etapeOrdre,
            'acteur_id' => $acteur?->id,
            'observation' => $observation,
            'categorie_rejet' => $categorieRejet,
            'meta' => $meta,
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    public function enregistrerDemarrage(WorkflowInstance $instance, Model $objet, User $user): AuditWorkflow
    {
        return $this->enregistrer('workflow_demarre', $instance, $user, $instance->etape_courante);
    }

    private function acte(string $action, ?string $libelleEtape): string
    {
        if ($action !== 'workflow_valide') {
            return match ($action) {
                'workflow_rejete' => 'REJET',
                'workflow_renvoye' => 'RETOUR',
                'workflow_demarre' => 'SOUMISSION',
                default => strtoupper(str_replace('workflow_', '', $action)),
            };
        }
        $l = mb_strtolower((string) $libelleEtape);
        foreach (self::ACTES as $motCle => $acte) {
            if (str_contains($l, $motCle)) {
                return $acte;
            }
        }

        return 'VALIDATION';
    }

    /** Données de décision sensibles : seuil ORD et sa version, autorité ; montant et reliquat PAY (JNL-015, JNL-016). */
    private function detailsMetier(?Model $objet): array
    {
        if ($objet instanceof Ordonnancement) {
            return [
                'montant_xaf' => (string) $objet->montant,
                'seuil_xaf' => $objet->seuil_xaf !== null ? (string) $objet->seuil_xaf : null,
                'seuil_version' => $objet->seuil_version,
                'assiette_seuil' => $objet->assiette_seuil,
                'autorite_competente' => $objet->ordonnateur_role,
            ];
        }
        if ($objet instanceof Paiement) {
            $ordonnance = (float) DB::table('ordonnancements')->where('id', $objet->ordonnancement_id)->value('montant');
            $paye = (float) DB::table('paiements')->where('ordonnancement_id', $objet->ordonnancement_id)
                ->whereNull('deleted_at')->whereIn('statut', ['valide', 'paye', 'execute'])->sum('montant');

            return [
                'montant_xaf' => (string) $objet->montant,
                'montant_ordonnance_xaf' => number_format($ordonnance, 2, '.', ''),
                'cumul_paye_xaf' => number_format($paye, 2, '.', ''),
                'reliquat_xaf' => number_format(max(0, $ordonnance - $paye), 2, '.', ''),
                'etat_execution' => $objet->etat_execution,
                'mode_paiement' => $objet->mode_paiement,
            ];
        }

        return isset($objet?->montant) ? ['montant_xaf' => (string) $objet->montant] : [];
    }
}
