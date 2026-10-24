<?php

namespace App\Services\Depense;

use App\Models\Paiement;
use App\Models\User;
use App\Models\WorkflowInstance;
use RuntimeException;

/** Changement de RIB : contrôle indépendant = visa Agent comptable ≠ saisie (PAY §5.2). */
class RibControleService
{
    /** @return array{message:string,rib_precedent_masque:string}|null */
    public function alerte(Paiement $paiement): ?array
    {
        $rib = trim((string) ($paiement->rib ?: ''));
        if ($rib === '' || $paiement->mode_paiement !== 'virement') {
            return null;
        }

        $precedent = Paiement::query()
            ->where('ordonnancement_id', $paiement->ordonnancement_id)
            ->where('id', '<', $paiement->id)
            ->whereNotNull('rib')
            ->where('rib', '!=', '')
            ->orderByDesc('id')
            ->first();

        if (! $precedent || hash_equals((string) $precedent->rib, $rib)) {
            return null;
        }

        return [
            'message' => 'Changement de RIB par rapport au paiement précédent. Le visa de l’Agent comptable doit être distinct de la saisie du nouveau RIB.',
            'rib_precedent_masque' => substr((string) $precedent->rib, 0, 4).'…',
        ];
    }

    public function enregistrerSaisie(Paiement $paiement, User $user): void
    {
        if (trim((string) ($paiement->rib ?: '')) === '') {
            return;
        }

        $paiement->forceFill(['rib_modifie_par' => $user->id])->save();
    }

    public function assertValidationIndependante(Paiement $paiement, User $user, WorkflowInstance $instance): void
    {
        if ($this->alerte($paiement) === null) {
            return;
        }

        $etape = $instance->etapeCourante();
        $roleAgent = (string) config('chaine_depense.role_agent_comptable', 'agent_comptable');
        if (! $etape || $etape->role !== $roleAgent) {
            return;
        }

        $saisiePar = $paiement->rib_modifie_par ?? $paiement->cree_par;
        if ((int) $saisiePar === (int) $user->id) {
            throw new RuntimeException(
                'Le changement de RIB exige un visa de l’Agent comptable distinct de l’agent qui a saisi le nouveau RIB.'
            );
        }
    }
}
