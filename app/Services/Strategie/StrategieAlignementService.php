<?php

namespace App\Services\Strategie;

use App\Models\Activite;
use App\Models\Pilier;
use App\Models\Strategie\AlignementStrategique;
use App\Models\Strategie\ProgrammationAnnuelle;
use App\Models\Strategie\ProgrammationPluriannuelle;
use App\Models\Strategie\ProgrammeStrategique;
use App\Models\Strategie\ProjetStrategique;
use Illuminate\Support\Collection;

class StrategieAlignementService
{
    /** Synthèse mémorisée (service « scoped » : une instance par requête) — elle était recalculée 3 fois par tableau de bord. */
    private ?array $synthese = null;

    /** @return Collection<int, array<string, mixed>> */
    public function matriceAlignement(?int $planStrategiqueId = null): Collection
    {
        $programmes = ProgrammeStrategique::query()
            ->with([
                'orientationStrategique.planStrategique.vision',
                'pilier',
                'projets.pilier',
                'projets.activites.sousProduit.produit.axe.pilier',
                'projets.activites.ligneBudgetaire',
            ])
            ->when($planStrategiqueId, fn ($q) => $q->whereHas(
                'orientationStrategique',
                fn ($o) => $o->where('plan_strategique_id', $planStrategiqueId)
            ))
            ->orderBy('code')
            ->get();

        $lignes = collect();

        foreach ($programmes as $programme) {
            $orientation = $programme->orientationStrategique;
            $plan = $orientation?->planStrategique;
            $vision = $plan?->vision;

            if ($programme->projets->isEmpty()) {
                $lignes->push($this->ligneMatrice(
                    $vision, $orientation, $programme, null, null,
                    $programme->pilier,
                    $this->estAligne($programme)
                ));
                continue;
            }

            foreach ($programme->projets as $projet) {
                if ($projet->activites->isEmpty()) {
                    $lignes->push($this->ligneMatrice(
                        $vision, $orientation, $programme, $projet, null,
                        $projet->pilier ?? $programme->pilier,
                        $this->estAligne($projet)
                    ));
                    continue;
                }

                foreach ($projet->activites as $activite) {
                    $lignes->push($this->ligneMatrice(
                        $vision, $orientation, $programme, $projet, $activite,
                        $activite->sousProduit?->produit?->axe?->pilier ?? $projet->pilier,
                        $this->estAligne($activite) || $activite->projet_id !== null
                    ));
                }
            }
        }

        return $lignes;
    }

    /** @return array<string, mixed> */
    private function ligneMatrice($vision, $orientation, $programme, $projet, $activite, $pilier, bool $aligne): array
    {
        return [
            'vision' => $vision?->reference ?? '—',
            'orientation' => $orientation?->code ?? '—',
            'programme' => $programme->code,
            'projet' => $projet?->code ?? '—',
            'activite' => $activite?->code ?? '—',
            'intitule' => $activite?->intitule ?? $projet?->intitule ?? $programme->intitule,
            'pilier_rbm' => $pilier?->code ?? '—',
            'budget' => (float) ($activite?->montant_total ?? $projet?->budget_previsionnel ?? $programme->budget_previsionnel ?? 0),
            'aligne' => $aligne,
        ];
    }

    private function estAligne(object $entite): bool
    {
        return AlignementStrategique::where('alignable_type', $entite::class)
            ->where('alignable_id', $entite->id)
            ->exists();
    }

    /** @return array<string, mixed> */
    public function syntheseAlignement(): array
    {
        return $this->synthese ??= $this->calculerSynthese();
    }

    /** @return array<string, mixed> */
    private function calculerSynthese(): array
    {
        $totalProgrammes = ProgrammeStrategique::count();
        $programmesOrientes = ProgrammeStrategique::whereHas('orientationStrategique')->count();
        $projets = ProjetStrategique::count();
        $projetsAvecPilier = ProjetStrategique::whereNotNull('pilier_id')->count();
        $piliers = Pilier::count();
        $piliersOrientes = Pilier::whereNotNull('orientation_strategique_id')->count();
        $activites = Activite::count();
        $activitesProjet = Activite::whereNotNull('projet_id')->count();
        $alignements = AlignementStrategique::count();

        $lignes = $this->matriceAlignement();
        $lignesAlignees = $lignes->where('aligne', true)->count();
        $tauxMatrice = $lignes->isNotEmpty()
            ? round(($lignesAlignees / $lignes->count()) * 100, 1)
            : 0.0;

        return [
            'alignements_enregistres' => $alignements,
            'taux_matrice' => $tauxMatrice,
            'lignes_matrice' => $lignes->count(),
            'programmes' => $totalProgrammes,
            'projets' => $projets,
            'piliers' => $piliers,
            'activites' => $activites,
            'programmes_orientes_pct' => $totalProgrammes > 0 ? round($programmesOrientes / $totalProgrammes * 100, 1) : 0,
            'projets_pilier_pct' => $projets > 0 ? round($projetsAvecPilier / $projets * 100, 1) : 0,
            'piliers_orientes_pct' => $piliers > 0 ? round($piliersOrientes / $piliers * 100, 1) : 0,
            'activites_projet_pct' => $activites > 0 ? round($activitesProjet / $activites * 100, 1) : 0,
        ];
    }

    /** @return Collection<int, Pilier> */
    public function piliersSansOrientation(): Collection
    {
        return Pilier::with('exercice')
            ->whereNull('orientation_strategique_id')
            ->orderBy('code')
            ->get();
    }

    /** @return array<string, mixed> */
    public function syntheseProgrammation(): array
    {
        $pluriannuelles = ProgrammationPluriannuelle::where('statut', 'actif')->count();
        $annuelles = ProgrammationAnnuelle::where('statut', 'actif')->count();

        $projection = ProgrammationPluriannuelle::with('programmationsAnnuelles.exercice')
            ->where('statut', 'actif')
            ->orderByDesc('annee_debut')
            ->first();

        return [
            'pluriannuelles_actives' => $pluriannuelles,
            'annuelles_actives' => $annuelles,
            'projection' => $projection,
        ];
    }

    public function rattacherPilierOrientation(Pilier $pilier, ?int $orientationId, ?int $programmeStrategiqueId = null): void
    {
        $pilier->update(['orientation_strategique_id' => $orientationId]);

        if ($programmeStrategiqueId) {
            ProgrammeStrategique::where('id', $programmeStrategiqueId)->update(['pilier_id' => $pilier->id]);
        }
    }

    public function rattacherActiviteProjet(Activite $activite, ?int $projetId): void
    {
        $activite->update(['projet_id' => $projetId]);
    }
}
