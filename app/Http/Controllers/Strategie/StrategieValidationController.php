<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Models\Strategie\PlanStrategique;
use App\Models\Workflow;
use App\Services\Strategie\StrategieValidationService;
use App\Services\WorkflowService;
use App\Support\WorkflowRejetCategorie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StrategieValidationController extends Controller
{
    public function __construct(
        private readonly StrategieValidationService $validation,
        private readonly WorkflowService $workflow,
    ) {
    }

    public function soumettre(PlanStrategique $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        try {
            if ($this->workflowActifDisponible() && ! $plan->workflowActif) {
                // Mêmes préconditions que le circuit simple (au moins une orientation et un objectif).
                if (! $this->validation->peutSoumettre($plan)) {
                    throw ValidationException::withMessages([
                        'plan' => 'Le plan doit être en brouillon/rejeté, avec au moins une orientation et un objectif.',
                    ]);
                }
                $this->workflow->demarrer($plan, 'STRATEGIE', auth()->user());
            } elseif (! $plan->workflowActif) {
                $this->validation->soumettre($plan);
            }
        } catch (RuntimeException|ValidationException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Plan soumis au circuit de validation.');
    }

    public function valider(Request $request, PlanStrategique $plan): RedirectResponse
    {
        $this->authorize('validate', $plan);

        $instance = $plan->workflowActif;
        if (! $instance) {
            try {
                $this->validation->valider($plan);
            } catch (ValidationException $e) {
                return back()->with('erreur', $e->getMessage());
            }

            return back()->with('succes', 'Decision enregistree sur le plan strategique.');
        }

        try {
            if ($this->workflow->peutAgir($instance, $request->user())) {
                $this->workflow->traiterDecision($instance, $request->user(), 'valider');
            } else {
                $this->validation->valider($plan);
            }
        } catch (RuntimeException|ValidationException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Decision enregistree sur le plan strategique.');
    }

    public function rejeter(Request $request, PlanStrategique $plan): RedirectResponse
    {
        $this->authorize('validate', $plan);

        $data = $request->validate([
            'motif_rejet' => ['nullable', 'string', 'max:1000'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'categorie_rejet' => ['nullable', 'string', 'max:50'],
        ]);
        $motif = $data['observation'] ?? $data['motif_rejet'] ?? null;

        $instance = $plan->workflowActif;
        if (! $instance) {
            try {
                $this->validation->rejeter($plan, $motif);
            } catch (ValidationException $e) {
                return back()->with('erreur', $e->getMessage());
            }

            return back()->with('succes', 'Plan rejete, retour en correction.');
        }

        try {
            $this->workflow->traiterDecision(
                $instance,
                $request->user(),
                'rejeter',
                $motif,
                $data['categorie_rejet'] ?? WorkflowRejetCategorie::ADMINISTRATIF,
            );
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('succes', 'Plan rejete, retour en correction.');
    }

    private function workflowActifDisponible(): bool
    {
        return Workflow::query()
            ->where('code', 'STRATEGIE')
            ->where('actif', true)
            ->exists();
    }
}
