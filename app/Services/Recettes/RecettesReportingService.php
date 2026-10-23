<?php

namespace App\Services\Recettes;

use App\Models\AppelFonds;
use App\Models\Encaissement;
use App\Models\EtatMembre;
use App\Models\PrevisionRecette;
use Illuminate\Support\Collection;

/** Phase R5 — jeux de données des états et rapports du module Recettes. */
class RecettesReportingService
{
    /** Tableau de mobilisation des contributions par État membre. */
    public function mobilisationParEtat(?int $exerciceId): Collection
    {
        $agreges = AppelFonds::query()
            ->selectRaw('etat_membre_id, SUM(montant_appele) appele, SUM(montant_regle) regle')
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('etat_membre_id')
            ->where('statut', '!=', 'annule')
            ->groupBy('etat_membre_id')
            ->get()
            ->keyBy('etat_membre_id');

        return EtatMembre::orderBy('pays')->get()->map(function (EtatMembre $etat) use ($agreges) {
            $row = $agreges->get($etat->id);
            $appele = (float) ($row->appele ?? 0);
            $regle = (float) ($row->regle ?? 0);

            return (object) [
                'pays' => $etat->pays,
                'code' => $etat->code,
                'cle_repartition' => (float) $etat->cle_repartition,
                'appele' => $appele,
                'recouvre' => $regle,
                'arriere' => max($appele - $regle, 0),
                'taux' => $appele > 0 ? round($regle / $appele * 100, 1) : 0.0,
            ];
        });
    }

    /** État des arriérés : appels de fonds dont le solde est non nul. */
    public function arrieres(?int $exerciceId): Collection
    {
        return AppelFonds::query()
            ->with(['etatMembre', 'sourceFinancement', 'exercice'])
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->where('statut', '!=', 'annule')
            ->whereColumn('montant_regle', '<', 'montant_appele')
            ->orderByDesc('montant_appele')
            ->get();
    }

    /** État des encaissements comptabilisés. */
    public function encaissements(?int $exerciceId): Collection
    {
        return Encaissement::query()
            ->with(['recette.exercice', 'recette.categorie', 'recette.etatMembre', 'validePar'])
            ->whereIn('statut', ['valide', 'rapproche'])
            ->when($exerciceId, fn ($q) => $q->whereHas('recette', fn ($r) => $r->where('exercice_id', $exerciceId)))
            ->orderByDesc('date_encaissement')
            ->get();
    }

    /** État des prévisions de recettes courantes. */
    public function previsions(?int $exerciceId): Collection
    {
        return PrevisionRecette::query()
            ->with(['exercice', 'categorie', 'sourceFinancement', 'etatMembre'])
            ->courante()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->orderBy('reference')
            ->get();
    }

    /** @return array{appele:float, recouvre:float, arriere:float, taux:float} */
    public function syntheseMobilisation(?int $exerciceId): array
    {
        $rows = $this->mobilisationParEtat($exerciceId);
        $appele = (float) $rows->sum('appele');
        $recouvre = (float) $rows->sum('recouvre');

        return [
            'appele' => $appele,
            'recouvre' => $recouvre,
            'arriere' => max($appele - $recouvre, 0),
            'taux' => $appele > 0 ? round($recouvre / $appele * 100, 1) : 0.0,
        ];
    }
}
