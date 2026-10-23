<?php

namespace App\Services\Pap;

use App\Models\Activite;
use App\Models\Pap;
use App\Models\PapActiviteProgrammation;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Models\Strategie\ProjetStrategique;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;

/** Intégrations P4 : alignement programmation ↔ activités PAP. */
class PapIntegrationsService
{
    /** Synchronise les liens activité ↔ programmation par projet/plan stratégique. */
    public function synchroniserAlignements(int $exerciceId): int
    {
        $count = 0;

        $activites = Activite::query()
            ->whereNotNull('projet_id')
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->get(['id', 'projet_id', 'code']);

        foreach ($activites as $activite) {
            $prog = $this->programmationPourActivite($activite, $exerciceId);
            if (! $prog) {
                continue;
            }

            $liaison = PapActiviteProgrammation::firstOrCreate(
                [
                    'programmation_annuelle_id' => $prog->id,
                    'activite_id' => $activite->id,
                ],
                ['mode' => 'auto'],
            );

            if ($liaison->wasRecentlyCreated) {
                $count++;
            }
        }

        if ($count > 0) {
            AuditLogger::enregistrer('pap_alignements_sync', Pap::firstWhere('exercice_id', $exerciceId), null, [
                'exercice_id' => $exerciceId,
                'nouvelles_liaisons' => $count,
            ]);
        }

        return $count;
    }

    /** @return array<string,mixed> */
    public function rapportAlignements(int $exerciceId): array
    {
        $activitesTotal = Activite::query()
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->count();

        $activitesProjet = Activite::query()
            ->whereNotNull('projet_id')
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->count();

        $liaisons = PapActiviteProgrammation::query()
            ->whereHas('activite.sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->with(['activite:id,code,intitule', 'programmation.programmationPluriannuelle.planStrategique'])
            ->get();

        $programmations = ProgrammationAnnuelle::with('programmationPluriannuelle.planStrategique', 'pap')
            ->where('exercice_id', $exerciceId)
            ->get();

        return [
            'activites_total' => $activitesTotal,
            'activites_projet' => $activitesProjet,
            'liaisons' => $liaisons,
            'liaisons_count' => $liaisons->count(),
            'taux_alignement' => $activitesTotal > 0
                ? round($liaisons->count() / $activitesTotal * 100, 1)
                : 0.0,
            'programmations' => $programmations,
            'activites_non_alignees' => $this->activitesNonAlignees($exerciceId, $liaisons),
        ];
    }

    /** @param  Collection<int, PapActiviteProgrammation>  $liaisons */
    /** @return Collection<int, Activite> */
    private function activitesNonAlignees(int $exerciceId, Collection $liaisons): Collection
    {
        $alignees = $liaisons->pluck('activite_id')->unique();

        return Activite::query()
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotIn('id', $alignees)
            ->orderBy('code')
            ->limit(20)
            ->get(['id', 'code', 'intitule', 'projet_id']);
    }

    private function programmationPourActivite(Activite $activite, int $exerciceId): ?ProgrammationAnnuelle
    {
        $projet = ProjetStrategique::with('programmeStrategique.orientationStrategique')->find($activite->projet_id);
        $planId = $projet?->programmeStrategique?->orientationStrategique?->plan_strategique_id;

        if (! $planId) {
            return ProgrammationAnnuelle::query()
                ->where('exercice_id', $exerciceId)
                ->whereNotNull('pap_id')
                ->first();
        }

        return ProgrammationAnnuelle::query()
            ->where('exercice_id', $exerciceId)
            ->whereHas('programmationPluriannuelle', fn ($q) => $q->where('plan_strategique_id', $planId))
            ->first()
            ?? ProgrammationAnnuelle::query()->where('exercice_id', $exerciceId)->first();
    }
}
