<?php

namespace App\Services\Budget;

use App\Models\Budget;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Notifications\Budget\BudgetWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Notifications du circuit budget (phase B1). */
class BudgetNotificationService
{
    public function notifierSoumission(Budget $budget, WorkflowInstance $instance): void
    {
        $etape = $instance->etapeCourante();
        if (! $etape) {
            return;
        }

        $destinataires = $this->utilisateursParRole($etape->role);
        if ($destinataires->isEmpty()) {
            return;
        }

        Notification::send($destinataires, new BudgetWorkflowNotification(
            $budget->fresh('exercice'),
            'soumission',
            'Budget soumis à validation',
            "Le budget {$budget->reference} ({$budget->exercice?->annee}) attend votre validation ({$etape->role}).",
            $etape->role,
        ));
    }

    public function notifierDecision(Budget $budget, WorkflowInstance $instance, string $decision, User $acteur): void
    {
        $budget = $budget->fresh('exercice');
        $libelleDecision = match ($decision) {
            'valider' => 'validée',
            'rejeter' => 'rejetée',
            'renvoyer' => 'renvoyée pour correction',
            default => $decision,
        };

        $destinataires = collect();
        if ($budget->responsable_id) {
            $destinataires->push(User::find($budget->responsable_id));
        }
        if ($budget->cree_par) {
            $destinataires->push(User::find($budget->cree_par));
        }

        $destinataires = $destinataires->filter()->unique('id')->reject(fn (User $u) => $u->id === $acteur->id);

        if ($destinataires->isNotEmpty()) {
            Notification::send($destinataires, new BudgetWorkflowNotification(
                $budget,
                $decision,
                "Étape budget {$libelleDecision}",
                "Le budget {$budget->reference} a été {$libelleDecision} par {$acteur->name}.",
            ));
        }

        if ($decision === 'valider' && $instance->fresh()->statut === 'en_cours') {
            $etape = $instance->fresh()->etapeCourante();
            if ($etape) {
                $suivants = $this->utilisateursParRole($etape->role);
                if ($suivants->isNotEmpty()) {
                    Notification::send($suivants, new BudgetWorkflowNotification(
                        $budget,
                        'etape_suivante',
                        'Budget — étape suivante',
                        "Le budget {$budget->reference} attend votre validation ({$etape->role}).",
                        $etape->role,
                    ));
                }
            }
        }
    }

    public function notifierAdoption(Budget $budget): void
    {
        $budget = $budget->fresh('exercice');
        $destinataires = User::query()
            ->where('actif', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                'president', 'secretaire_general', 'commissaire', 'directeur', 'chef_service', 'gestionnaire_budgetaire',
            ]))
            ->get();

        if ($destinataires->isEmpty()) {
            return;
        }

        Notification::send($destinataires, new BudgetWorkflowNotification(
            $budget,
            'adoption',
            'Budget adopté',
            "Le budget {$budget->reference} ({$budget->exercice?->annee}) est adopté et opérationnel.",
        ));
    }

    /** @return Collection<int,User> */
    private function utilisateursParRole(string $role): Collection
    {
        return User::query()
            ->where('actif', true)
            ->role($role)
            ->get();
    }
}
