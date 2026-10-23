<?php

namespace App\Services\Pap;

use App\Models\Activite;
use App\Models\Collecte;
use App\Models\Pap;
use App\Models\PapMesureCorrective;
use App\Models\PapRisque;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Gestion GAR P3 : risques, mesures correctives, causes d'écarts. */
class PapGarService
{
    /** @param  array<string,mixed>  $data */
    public function creerRisque(Pap $pap, array $data): PapRisque
    {
        $this->assertGarModifiable($pap);

        $risque = PapRisque::create([
            'pap_id' => $pap->id,
            'activite_id' => $data['activite_id'] ?? null,
            'code' => $data['code'],
            'libelle' => $data['libelle'],
            'description' => $data['description'] ?? null,
            'probabilite' => $data['probabilite'] ?? 'moyenne',
            'impact' => $data['impact'] ?? 'moyen',
            'statut' => $data['statut'] ?? 'ouvert',
            'mitigation' => $data['mitigation'] ?? null,
            'responsable_user_id' => $data['responsable_user_id'] ?? null,
        ]);

        AuditLogger::enregistrer('pap_risque_cree', $risque, null, ['code' => $risque->code]);

        return $risque;
    }

    /** @param  array<string,mixed>  $data */
    public function mettreAJourRisque(PapRisque $risque, array $data): PapRisque
    {
        $this->assertGarModifiable($risque->pap);

        $risque->update([
            'activite_id' => $data['activite_id'] ?? null,
            'libelle' => $data['libelle'],
            'description' => $data['description'] ?? null,
            'probabilite' => $data['probabilite'],
            'impact' => $data['impact'],
            'statut' => $data['statut'],
            'mitigation' => $data['mitigation'] ?? null,
            'responsable_user_id' => $data['responsable_user_id'] ?? null,
        ]);

        return $risque->fresh();
    }

    public function supprimerRisque(PapRisque $risque): void
    {
        $this->assertGarModifiable($risque->pap);
        $risque->delete();
    }

    /** @param  array<string,mixed>  $data */
    public function creerMesure(Pap $pap, array $data): PapMesureCorrective
    {
        $this->assertGarModifiable($pap);

        $mesure = PapMesureCorrective::create([
            'pap_id' => $pap->id,
            'pap_risque_id' => $data['pap_risque_id'] ?? null,
            'activite_id' => $data['activite_id'] ?? null,
            'code' => $data['code'] ?? null,
            'description' => $data['description'],
            'echeance' => $data['echeance'] ?? null,
            'statut' => $data['statut'] ?? 'planifiee',
            'responsable_user_id' => $data['responsable_user_id'] ?? null,
        ]);

        AuditLogger::enregistrer('pap_mesure_creee', $mesure);

        return $mesure;
    }

    /** @param  array<string,mixed>  $data */
    public function mettreAJourMesure(PapMesureCorrective $mesure, array $data): PapMesureCorrective
    {
        $this->assertGarModifiable($mesure->pap);

        $mesure->update([
            'pap_risque_id' => $data['pap_risque_id'] ?? null,
            'activite_id' => $data['activite_id'] ?? null,
            'description' => $data['description'],
            'echeance' => $data['echeance'] ?? null,
            'statut' => $data['statut'],
            'resultat' => $data['resultat'] ?? null,
            'responsable_user_id' => $data['responsable_user_id'] ?? null,
        ]);

        return $mesure->fresh();
    }

    /** @param  array<int,array{cause_ecart?:string,mesure_corrective?:string}>  $ecarts */
    public function enregistrerEcartsActivites(Pap $pap, array $ecarts): int
    {
        $this->assertGarModifiable($pap);
        $count = 0;

        DB::transaction(function () use ($pap, $ecarts, &$count) {
            foreach ($ecarts as $activiteId => $payload) {
                $activite = Activite::query()
                    ->where('id', $activiteId)
                    ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $pap->exercice_id))
                    ->first();

                if (! $activite) {
                    continue;
                }

                $activite->update([
                    'cause_ecart' => $payload['cause_ecart'] ?? $activite->cause_ecart,
                    'mesure_corrective' => $payload['mesure_corrective'] ?? $activite->mesure_corrective,
                ]);
                $count++;
            }
        });

        if ($count > 0) {
            AuditLogger::enregistrer('pap_ecarts_enregistres', $pap, null, ['activites' => $count]);
        }

        return $count;
    }

    /** @param  array<int,string>  $causes */
    public function enregistrerEcartsCollectes(Pap $pap, array $causes): int
    {
        $this->assertGarModifiable($pap);
        $count = 0;

        foreach ($causes as $collecteId => $cause) {
            $collecte = Collecte::query()
                ->where('id', $collecteId)
                ->whereHas('activite.sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $pap->exercice_id))
                ->first();

            if (! $collecte) {
                continue;
            }

            $collecte->update(['cause_ecart' => $cause]);
            $count++;
        }

        return $count;
    }

    private function assertGarModifiable(Pap $pap): void
    {
        if (! in_array($pap->statut, ['brouillon', 'rejete', 'en_revision', 'en_validation', 'valide', 'publie'], true)) {
            throw new RuntimeException('PAP non modifiable pour le suivi GAR.');
        }

        if ($pap->statut === 'publie' && ! auth()->user()?->can('pap.modifier')) {
            throw new RuntimeException('Modification GAR réservée aux gestionnaires PAP.');
        }
    }
}
