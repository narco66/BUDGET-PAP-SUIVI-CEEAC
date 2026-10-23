<?php

namespace App\Services\Pap;

use App\Models\Exercice;
use App\Models\Pap;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** CRUD enveloppe PAP (phase P2). */
class PapEnveloppeService
{
    /** @param  array<string,mixed>  $data */
    public function creer(array $data, int $userId): Pap
    {
        return DB::transaction(function () use ($data, $userId) {
            $exercice = Exercice::findOrFail($data['exercice_id']);

            if (Pap::where('exercice_id', $exercice->id)->exists()) {
                throw new RuntimeException("Un PAP existe déjà pour l'exercice {$exercice->annee}.");
            }

            $pap = Pap::create([
                'exercice_id' => $exercice->id,
                'reference' => $data['reference'] ?? $this->genererReference($exercice),
                'libelle' => $data['libelle'],
                'structure_id' => $data['structure_id'] ?? null,
                'responsable_id' => $data['responsable_id'] ?? null,
                'date_elaboration' => $data['date_elaboration'] ?? now()->toDateString(),
                'observations' => $data['observations'] ?? null,
                'statut' => 'brouillon',
                'version' => 1,
                'cree_par' => $userId,
            ]);

            AuditLogger::enregistrer('pap_cree', $pap, null, ['reference' => $pap->reference]);

            return $pap;
        });
    }

    /** @param  array<string,mixed>  $data */
    public function mettreAJour(Pap $pap, array $data): Pap
    {
        if (! $pap->estModifiable() && ! auth()->user()?->hasRole('administrateur')) {
            throw new RuntimeException('Ce PAP ne peut plus être modifié.');
        }

        $pap->update([
            'libelle' => $data['libelle'],
            'structure_id' => $data['structure_id'] ?? null,
            'responsable_id' => $data['responsable_id'] ?? null,
            'date_elaboration' => $data['date_elaboration'] ?? $pap->date_elaboration,
            'observations' => $data['observations'] ?? null,
        ]);

        AuditLogger::enregistrer('pap_modifie', $pap->fresh());

        return $pap->fresh();
    }

    public function supprimer(Pap $pap): void
    {
        if (! $pap->peutEtreSupprime()) {
            throw new RuntimeException('Suppression interdite : PAP validé, publié ou lié au budget.');
        }

        if ($pap->workflowActif) {
            throw new RuntimeException('Suppression interdite : circuit de validation en cours.');
        }

        AuditLogger::enregistrer('pap_supprime', $pap, null, ['reference' => $pap->reference]);
        $pap->delete();
    }

    public function genererReference(Exercice $exercice): string
    {
        return 'PAP-'.$exercice->annee;
    }
}
