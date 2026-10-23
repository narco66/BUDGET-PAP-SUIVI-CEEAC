<?php

namespace App\Services\Strategie;

use App\Models\Strategie\AlignementStrategique;
use App\Models\Strategie\OrientationStrategique;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\PortefeuilleProjet;
use App\Models\Strategie\IndicateurStrategique;
use App\Models\Strategie\ObjectifStrategique;
use App\Models\Strategie\RisqueStrategique;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use App\Models\Strategie\Vision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class StrategieReferentielService
{
    /** @return array<string, mixed> */
    public function syntheseHub(?StrategieAlignementService $alignement = null): array
    {
        $alignement ??= app(StrategieAlignementService::class);
        $alignStats = $alignement->syntheseAlignement();
        $progStats = $alignement->syntheseProgrammation();

        $plansActifs = PlanStrategique::where('statut', 'actif')->count();
        $programmes = ProgrammeStrategique::count();
        $projets = ProjetStrategique::count();
        $portefeuilles = PortefeuilleProjet::where('statut', 'actif')->count();

        $budgetProgramme = (float) ProgrammeStrategique::sum('budget_previsionnel');
        $budgetProjets = (float) ProjetStrategique::sum('budget_previsionnel');
        $budgetMobilise = (float) ProjetStrategique::sum('budget_engage');

        return [
            'visions_actives' => Vision::where('statut', 'actif')->count(),
            'plans_actifs' => $plansActifs,
            'orientations' => OrientationStrategique::where('statut', 'actif')->count(),
            'programmes' => $programmes,
            'projets' => $projets,
            'portefeuilles' => $portefeuilles,
            'objectifs' => ObjectifStrategique::where('statut', 'actif')->count(),
            'indicateurs' => IndicateurStrategique::where('statut', 'actif')->count(),
            'risques' => RisqueStrategique::whereIn('statut', ['identifie', 'en_traitement'])->count(),
            'taux_alignement' => $alignStats['taux_matrice'],
            'budget_programme' => $budgetProgramme,
            'budget_projets' => $budgetProjets,
            'budget_mobilise' => $budgetMobilise,
            'programmations_pluri' => $progStats['pluriannuelles_actives'],
            'programmations_annuelles' => $progStats['annuelles_actives'],
            'piliers_orientes_pct' => $alignStats['piliers_orientes_pct'],
            'activites_projet_pct' => $alignStats['activites_projet_pct'],
        ];
    }

    public function archiver(Model $model): void
    {
        if (! in_array('statut', $model->getFillable(), true)) {
            return;
        }

        $model->update([
            'statut' => 'archive',
            'updated_by' => auth()->id(),
        ]);
    }

    public function restaurer(Model $model): void
    {
        if (! in_array('statut', $model->getFillable(), true)) {
            return;
        }

        $statut = match ($model::class) {
            ProjetStrategique::class => 'planifie',
            default => 'actif',
        };

        $model->update([
            'statut' => $statut,
            'updated_by' => auth()->id(),
        ]);
    }

    public function catalogueVisions(): Collection
    {
        return Vision::orderBy('reference')->get([
            'reference', 'intitule', 'periode', 'date_debut', 'date_fin', 'statut', 'version',
        ]);
    }

    public function cataloguePlans(): Collection
    {
        return PlanStrategique::with('vision:id,reference,intitule')
            ->orderBy('reference')
            ->get(['id', 'reference', 'intitule', 'vision_id', 'date_debut', 'date_fin', 'statut', 'version']);
    }

    public function catalogueOrientations(): Collection
    {
        return OrientationStrategique::with('planStrategique:id,reference,intitule')
            ->orderBy('ordre')
            ->get(['id', 'plan_strategique_id', 'code', 'intitule', 'ordre', 'statut']);
    }

    public function catalogueProgrammes(): Collection
    {
        return ProgrammeStrategique::with('orientationStrategique:id,code,intitule')
            ->orderBy('code')
            ->get([
                'id', 'orientation_strategique_id', 'code', 'intitule', 'budget_previsionnel',
                'date_debut', 'date_fin', 'statut', 'version',
            ]);
    }

    public function catalogueProjets(): Collection
    {
        return ProjetStrategique::with('programmeStrategique:id,code,intitule')
            ->orderBy('code')
            ->get([
                'id', 'programme_strategique_id', 'code', 'intitule', 'statut', 'cycle_vie',
                'budget_previsionnel', 'budget_engage', 'budget_consomme', 'date_debut', 'date_fin',
            ]);
    }

    public function cataloguePortefeuilles(): Collection
    {
        return PortefeuilleProjet::withCount('projets')
            ->orderBy('reference')
            ->get(['id', 'reference', 'intitule', 'statut', 'exercice_id']);
    }

    public function catalogueObjectifs(): Collection
    {
        return ObjectifStrategique::with(['planStrategique:id,reference', 'orientationStrategique:id,code'])
            ->withCount('indicateurs')
            ->orderBy('ordre')
            ->get(['id', 'plan_strategique_id', 'orientation_strategique_id', 'code', 'intitule', 'ordre', 'statut']);
    }

    public function catalogueIndicateurs(): Collection
    {
        return IndicateurStrategique::with('objectifStrategique:id,code,plan_strategique_id')
            ->orderBy('code')
            ->get([
                'id', 'objectif_strategique_id', 'code', 'libelle', 'unite', 'periodicite',
                'valeur_reference', 'valeur_cible', 'seuil_alerte', 'statut',
            ]);
    }

    public function catalogueRisques(): Collection
    {
        return RisqueStrategique::with('planStrategique:id,reference')
            ->orderBy('code')
            ->get(['id', 'plan_strategique_id', 'code', 'description', 'probabilite', 'impact', 'statut']);
    }
}
