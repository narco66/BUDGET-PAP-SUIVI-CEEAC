<?php

namespace App\Http\Resources;

use App\Models\WorkflowTache;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Workflow\TacheActionService;
use App\Services\Workflow\TacheQueryService;
use App\Services\Workflow\TacheWorkflowService;
use App\Support\WorkflowObjetUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowTache */
class WorkflowTacheResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var WorkflowTache $t */
        $t = $this->resource;
        $user = $request->user();
        $requetes = app(TacheQueryService::class);
        $eligibilite = $user ? $requetes->eligibilite($t, $user) : ['ok' => false, 'delegue' => false, 'motif' => null];
        $actions = $user ? app(TacheActionService::class)->actionsDisponibles($t, $user) : ['consulter'];

        return [
            'id' => $t->id,
            'reference' => $t->reference,
            'nature' => $t->nature,
            'action_code' => $t->action_code,
            'action_attendue' => $t->estPreparation() ? $t->libelle_etape : app(TacheActionService::class)->libelleValidation($t),
            'affectation_defaut' => (bool) $t->affectation_defaut,
            'initiateur' => ($i = $requetes->initiateur($t)) ? ['id' => $i->id, 'nom' => $i->name] : null,
            'module' => $t->module,
            'circuit' => $t->workflow_code,
            'dossier' => [
                'type' => class_basename($t->objet_type),
                'id' => $t->objet_id,
                'reference' => $t->objet?->numero ?? $t->objet?->code ?? null,
                'libelle' => $t->objet_libelle,
                'url' => NotificationDispatcher::lienRelatif(WorkflowObjetUrls::show($t->objet)),
                'montant' => $requetes->montant($t),
                'statut' => $t->objet?->statut ?? null,
                'cree_le' => $t->objet?->created_at?->toIso8601String(),
            ],
            'etape' => ['ordre' => $t->etape_ordre, 'role' => $t->role, 'libelle' => $t->libelle_etape],
            'unite' => $t->structure ? ['id' => $t->structure->id, 'code' => $t->structure->code, 'libelle' => $t->structure->libelle] : null,
            'statut' => $t->statut,
            'statut_libelle' => WorkflowTache::STATUTS[$t->statut] ?? $t->statut,
            'priorite' => app(TacheWorkflowService::class)->prioriteEffective($t),
            'priorite_manuelle' => $t->priorite_manuelle,
            'decision' => $t->decision,
            'affectataire' => $t->assigne ? ['id' => $t->assigne->id, 'nom' => $t->assigne->name] : null,
            'termine_par' => $t->terminePar ? ['id' => $t->terminePar->id, 'nom' => $t->terminePar->name] : null,
            'recue_le' => $t->created_at?->toIso8601String(),
            'pris_en_charge_le' => $t->pris_en_charge_le?->toIso8601String(),
            'echeance_le' => $t->echeance_le?->toIso8601String(),
            'termine_le' => $t->termine_le?->toIso8601String(),
            'en_retard' => $t->enRetard(),
            'motif_suspension' => $t->motif_suspension,
            'par_delegation' => $eligibilite['delegue'],
            'motif_non_traitable' => $eligibilite['ok'] ? null : $eligibilite['motif'],
            'libelle_validation' => app(TacheActionService::class)->libelleValidation($t),
            'version' => $t->version,
            'available_actions' => $actions,
            'url' => route('taches.show', $t, false),
            // Lien absolu vers la fiche Blade pour la SPA (coexistence frontend / backend).
            'url_legacy' => rtrim((string) config('spa.legacy_url', config('app.url')), '/').route('taches.show', $t, false),
        ];
    }
}
