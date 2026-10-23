<?php

namespace App\Services\Recettes;

use App\Models\ConventionFinancement;
use App\Models\DecaissementConvention;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Phase R8 — décaissements des conventions PTF et rattachement aux activités du PAP. */
class DecaissementConventionService
{
    public function enregistrer(ConventionFinancement $convention, array $data, ?User $user = null): DecaissementConvention
    {
        $montant = (float) $data['montant'];
        $taux = (float) ($data['taux_change'] ?? 1);
        $montantXaf = round($montant * $taux, 2);
        $statut = $data['statut'] ?? 'execute';

        // Le cumul exécuté ne peut pas dépasser le montant de l'accord.
        if ($statut === 'execute') {
            $cumul = (float) $convention->montant_decaisse + $montantXaf;
            if ($cumul > (float) $convention->montant_accord + 0.009) {
                throw new RuntimeException('Le décaissement cumulé dépasse le montant de l\'accord.');
            }
        }

        return DB::transaction(function () use ($convention, $data, $user, $montant, $taux, $montantXaf, $statut) {
            $decaissement = DecaissementConvention::create([
                'convention_id' => $convention->id,
                'activite_id' => $data['activite_id'] ?? null,
                'reference' => $data['reference'],
                'objet' => $data['objet'],
                'montant' => $montant,
                'devise' => $data['devise'] ?? 'XAF',
                'taux_change' => $taux,
                'montant_xaf' => $montantXaf,
                'date_decaissement' => $data['date_decaissement'],
                'statut' => $statut,
                'observations' => $data['observations'] ?? null,
                'saisi_par' => $user?->id,
            ]);

            $convention->recalculerDecaisse();

            return $decaissement;
        });
    }

    public function annuler(DecaissementConvention $decaissement): DecaissementConvention
    {
        if ($decaissement->statut === 'annule') {
            throw new RuntimeException('Décaissement déjà annulé.');
        }

        return DB::transaction(function () use ($decaissement) {
            $decaissement->update(['statut' => 'annule']);
            $decaissement->convention->recalculerDecaisse();

            return $decaissement->refresh();
        });
    }
}
