<?php

namespace App\Notifications\Budget;

use App\Models\Budget;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Notification in-app du circuit de validation budget. */
class BudgetWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Budget $budget,
        public readonly string $type,
        public readonly string $titre,
        public readonly string $message,
        public readonly ?string $roleEtape = null,
    ) {
    }

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'titre' => $this->titre,
            'message' => $this->message,
            'budget_id' => $this->budget->id,
            'budget_reference' => $this->budget->reference,
            'exercice' => $this->budget->exercice?->annee,
            'statut' => $this->budget->statut,
            'role_etape' => $this->roleEtape,
            'url' => route('budget.enveloppe.show', $this->budget),
        ];
    }
}
