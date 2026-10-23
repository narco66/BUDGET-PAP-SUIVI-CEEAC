<?php

namespace App\Services\Pap;

use App\Models\Pap;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Notifications\Pap\PapWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/** Notifications du circuit PAP (phase P2). */
class PapNotificationService
{
    public function notifierSoumission(Pap $pap, WorkflowInstance $instance): void
    {
        $etape = $instance->etapeCourante();
        if (! $etape) {
            return;
        }

        $destinataires = $this->utilisateursParRole($etape->role);
        if ($destinataires->isEmpty()) {
            return;
        }

        Notification::send($destinataires, new PapWorkflowNotification(
            $pap->fresh('exercice'),
            'soumission',
            'PAP soumis à validation',
            "Le PAP {$pap->reference} ({$pap->exercice?->annee}) attend votre validation ({$etape->role}).",
            $etape->role,
        ));
    }

    public function notifierDecision(Pap $pap, WorkflowInstance $instance, string $decision, User $acteur): void
    {
        $pap = $pap->fresh('exercice');
        $libelleDecision = match ($decision) {
            'valider' => 'validée',
            'rejeter' => 'rejetée',
            'renvoyer' => 'renvoyée pour correction',
            default => $decision,
        };

        $destinataires = collect();
        if ($pap->responsable_id) {
            $destinataires->push(User::find($pap->responsable_id));
        }
        if ($pap->cree_par) {
            $destinataires->push(User::find($pap->cree_par));
        }

        $destinataires = $destinataires->filter()->unique('id')->reject(fn (User $u) => $u->id === $acteur->id);

        if ($destinataires->isNotEmpty()) {
            Notification::send($destinataires, new PapWorkflowNotification(
                $pap,
                $decision,
                "Étape PAP {$libelleDecision}",
                "Le PAP {$pap->reference} a été {$libelleDecision} par {$acteur->name}.",
            ));
        }

        if ($decision === 'valider' && $instance->fresh()->statut === 'en_cours') {
            $etape = $instance->fresh()->etapeCourante();
            if ($etape) {
                $suivants = $this->utilisateursParRole($etape->role);
                if ($suivants->isNotEmpty()) {
                    Notification::send($suivants, new PapWorkflowNotification(
                        $pap,
                        'etape_suivante',
                        'PAP — étape suivante',
                        "Le PAP {$pap->reference} attend votre validation ({$etape->role}).",
                        $etape->role,
                    ));
                }
            }
        }
    }

    public function notifierPublication(Pap $pap): void
    {
        $pap = $pap->fresh('exercice');
        $destinataires = User::query()
            ->where('actif', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                'president', 'secretaire_general', 'commissaire', 'directeur', 'chef_service',
            ]))
            ->get();

        if ($destinataires->isEmpty()) {
            return;
        }

        Notification::send($destinataires, new PapWorkflowNotification(
            $pap,
            'publication',
            'PAP publié',
            "Le Plan Annuel de Performance {$pap->reference} ({$pap->exercice?->annee}) est publié et opérationnel.",
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
