<?php

namespace App\Services\Recettes;

use App\Models\AppelFonds;
use App\Models\EcheanceApurement;
use App\Models\PlanApurement;
use App\Models\User;
use App\Support\Numero;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Phase R7 — plans d'apurement : échéancier de règlement des arriérés. */
class PlanApurementService
{
    public function __construct(private readonly AppelFondsService $appels)
    {
    }

    /**
     * Établit un plan d'apurement répartissant l'arriéré de l'appel en N échéances mensuelles.
     */
    public function creer(AppelFonds $appel, array $data, ?User $user = null): PlanApurement
    {
        if (in_array($appel->statut, ['solde', 'annule'], true)) {
            throw new RuntimeException('Aucun plan possible : l\'appel de fonds est soldé ou annulé.');
        }

        if ($appel->planApurementActif()) {
            throw new RuntimeException('Un plan d\'apurement actif existe déjà pour cet appel.');
        }

        $solde = $appel->solde;
        if ($solde <= 0) {
            throw new RuntimeException('Aucun arriéré à apurer.');
        }

        $nombre = max((int) ($data['nombre_echeances'] ?? 1), 1);
        $debut = Carbon::parse($data['date_debut']);

        return DB::transaction(function () use ($appel, $data, $user, $solde, $nombre, $debut) {
            $plan = PlanApurement::create([
                'appel_fonds_id' => $appel->id,
                'reference' => $data['reference'] ?? Numero::suivant('plans_apurement', 'APUR', (int) $debut->year, 'reference'),
                'montant_total' => $solde,
                'nombre_echeances' => $nombre,
                'date_debut' => $debut->toDateString(),
                'statut' => 'actif',
                'observations' => $data['observations'] ?? null,
                'etabli_par' => $user?->id,
            ]);

            $base = round($solde / $nombre, 2);
            $cumul = 0;
            for ($rang = 1; $rang <= $nombre; $rang++) {
                // La dernière échéance absorbe l'arrondi pour boucler exactement le total.
                $montant = $rang === $nombre ? round($solde - $cumul, 2) : $base;
                $cumul += $montant;

                EcheanceApurement::create([
                    'plan_apurement_id' => $plan->id,
                    'rang' => $rang,
                    'date_echeance' => $debut->copy()->addMonths($rang - 1)->toDateString(),
                    'montant_prevu' => $montant,
                    'montant_regle' => 0,
                    'statut' => 'a_venir',
                ]);
            }

            return $plan->load('echeances');
        });
    }

    /**
     * Enregistre le règlement d'une échéance : met à jour l'échéance ET reporte
     * le recouvrement sur l'appel de fonds (source unique du réalisé).
     */
    public function reglerEcheance(EcheanceApurement $echeance, float $montant, ?string $observations = null): EcheanceApurement
    {
        if ($montant <= 0) {
            throw new RuntimeException('Le montant réglé doit être positif.');
        }

        $plan = $echeance->plan;
        if ($plan->statut !== 'actif') {
            throw new RuntimeException('Plan d\'apurement clos : aucun règlement possible.');
        }

        $nouveau = (float) $echeance->montant_regle + $montant;
        if ($nouveau > (float) $echeance->montant_prevu + 0.009) {
            throw new RuntimeException('Le règlement dépasse le montant de l\'échéance.');
        }

        return DB::transaction(function () use ($echeance, $plan, $montant, $nouveau, $observations) {
            $echeance->update([
                'montant_regle' => $nouveau,
                'statut' => $nouveau + 0.009 >= (float) $echeance->montant_prevu ? 'honoree' : 'partielle',
                'date_reglement' => now()->toDateString(),
            ]);

            // Report sur l'appel de fonds (alimente le réalisé / arriéré).
            $this->appels->enregistrerRecouvrement($plan->appel, $montant, $observations);

            if ($plan->solde() <= 0.009) {
                $plan->update(['statut' => 'solde']);
            }

            return $echeance->refresh();
        });
    }

    public function annuler(PlanApurement $plan): PlanApurement
    {
        if ($plan->estVerrouille()) {
            throw new RuntimeException('Un plan ayant des règlements ne peut pas être annulé.');
        }

        $plan->update(['statut' => 'annule']);

        return $plan->refresh();
    }
}
