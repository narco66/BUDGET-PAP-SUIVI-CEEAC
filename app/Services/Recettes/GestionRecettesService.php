<?php

namespace App\Services\Recettes;

use App\Models\BudgetRubrique;
use App\Models\CategorieRecette;
use App\Models\Contribution;
use App\Models\Exercice;
use App\Models\PrevisionRecette;
use App\Models\Recette;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Phase R1 — catégories, prévisions et reprise depuis `contributions`. */
class GestionRecettesService
{
    /** @return array<string, CategorieRecette> */
    public function assurerCategories(): array
    {
        $definitions = [
            ['CAT-EM', 'Contributions statutaires États membres', 'contribution_statutaire', 10],
            ['CAT-PTF', 'Contributions partenaires techniques et financiers', 'contribution_ptf', 20],
            ['CAT-RC', 'Recettes communautaires', 'recette_communautaire', 30],
            ['CAT-PF', 'Produits financiers', 'produit_financier', 40],
            ['CAT-SUB', 'Subventions et appuis budgétaires', 'subvention', 50],
        ];

        $categories = [];
        foreach ($definitions as [$code, $libelle, $type, $ordre]) {
            $categories[$code] = CategorieRecette::updateOrCreate(
                ['code' => $code],
                [
                    'libelle' => $libelle,
                    'type' => $type,
                    'ordre' => $ordre,
                    'actif' => true,
                    'description' => 'Catégorie de recettes — phase R1.',
                ]
            );
        }

        return $categories;
    }

    /** @return array{previsions:int, recettes:int} */
    public function synchroniserDepuisContributions(?int $exerciceId = null): array
    {
        $this->assurerCategories();

        $compteurs = ['previsions' => 0, 'recettes' => 0];

        Contribution::query()
            ->with(['etatMembre', 'sourceFinancement'])
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->orderBy('id')
            ->each(function (Contribution $contribution) use (&$compteurs) {
                $prevision = $this->upsertPrevisionDepuisContribution($contribution);
                if ($prevision->wasRecentlyCreated) {
                    $compteurs['previsions']++;
                }

                $recette = $this->upsertRecetteDepuisPrevision($prevision, $contribution);
                if ($recette->wasRecentlyCreated) {
                    $compteurs['recettes']++;
                }
            });

        return $compteurs;
    }

    public function creerPrevision(array $data): PrevisionRecette
    {
        $exercice = Exercice::findOrFail($data['exercice_id']);
        if ($exercice->estClos()) {
            throw new RuntimeException('Impossible de créer une prévision sur un exercice clos.');
        }

        return PrevisionRecette::create([
            'exercice_id' => $exercice->id,
            'reference' => $data['reference'],
            'categorie_recette_id' => $data['categorie_recette_id'] ?? null,
            'source_financement_id' => $data['source_financement_id'] ?? null,
            'etat_membre_id' => $data['etat_membre_id'] ?? null,
            'rubrique_id' => $data['rubrique_id'] ?? null,
            'montant_prevu' => $data['montant_prevu'] ?? 0,
            'montant_revise' => $data['montant_revise'] ?? null,
            'version' => 1,
            'scenario' => $data['scenario'] ?? 'initial',
            'est_courante' => true,
            'statut' => $data['statut'] ?? 'brouillon',
            'observations' => $data['observations'] ?? null,
            'cree_par' => $data['cree_par'] ?? null,
        ]);
    }

    public function reviserPrevision(PrevisionRecette $courante, float $montantRevise, ?string $observations = null): PrevisionRecette
    {
        if (! $courante->est_courante) {
            throw new InvalidArgumentException('Seule la version courante peut être révisée.');
        }

        return DB::transaction(function () use ($courante, $montantRevise, $observations) {
            $courante->update([
                'est_courante' => false,
                'statut' => 'revise',
            ]);

            $nouvelle = PrevisionRecette::create([
                'exercice_id' => $courante->exercice_id,
                'reference' => $courante->reference.'-V'.($courante->version + 1),
                'categorie_recette_id' => $courante->categorie_recette_id,
                'source_financement_id' => $courante->source_financement_id,
                'etat_membre_id' => $courante->etat_membre_id,
                'rubrique_id' => $courante->rubrique_id,
                'contribution_id' => null,
                'version' => $courante->version + 1,
                'scenario' => 'revise',
                'est_courante' => true,
                'montant_prevu' => $courante->montant_prevu,
                'montant_revise' => $montantRevise,
                'statut' => 'valide',
                'observations' => $observations,
            ]);

            if ($recette = $courante->recette) {
                $recette->update([
                    'prevision_recette_id' => $nouvelle->id,
                    'montant_prevu' => $montantRevise,
                    'statut' => $this->statutRecettePourMontants((float) $recette->montant_realise, $montantRevise),
                ]);
            }

            return $nouvelle;
        });
    }

    /** @return array{previsions:int, recettes:int, montant_prevu:float, montant_realise:float} */
    public function syntheseExercice(int $exerciceId): array
    {
        $previsions = PrevisionRecette::query()
            ->where('exercice_id', $exerciceId)
            ->courante()
            ->valide();

        $recettes = Recette::query()->where('exercice_id', $exerciceId);

        return [
            'previsions' => (clone $previsions)->count(),
            'recettes' => (clone $recettes)->count(),
            'montant_prevu' => (float) (clone $previsions)->get()->sum(fn (PrevisionRecette $p) => $p->montant_retenu),
            'montant_realise' => (float) (clone $recettes)->sum('montant_realise'),
        ];
    }

    private function upsertPrevisionDepuisContribution(Contribution $contribution): PrevisionRecette
    {
        $categorie = $this->categoriePourContribution($contribution);

        return PrevisionRecette::updateOrCreate(
            ['contribution_id' => $contribution->id],
            [
                'exercice_id' => $contribution->exercice_id,
                'reference' => $this->referencePourContribution($contribution),
                'categorie_recette_id' => $categorie->id,
                'source_financement_id' => $contribution->source_financement_id,
                'etat_membre_id' => $contribution->etat_membre_id,
                'rubrique_id' => $this->rubriquePourContribution($contribution)?->id,
                'version' => 1,
                'scenario' => 'initial',
                'est_courante' => true,
                'montant_prevu' => $contribution->montant_appele,
                'montant_revise' => null,
                'statut' => 'valide',
                'observations' => 'Reprise automatique depuis contributions (phase R1).',
            ]
        );
    }

    private function upsertRecetteDepuisPrevision(PrevisionRecette $prevision, Contribution $contribution): Recette
    {
        return Recette::updateOrCreate(
            ['prevision_recette_id' => $prevision->id],
            [
                'exercice_id' => $contribution->exercice_id,
                'reference' => 'REC-'.$prevision->reference,
                'categorie_recette_id' => $prevision->categorie_recette_id,
                'source_financement_id' => $contribution->source_financement_id,
                'etat_membre_id' => $contribution->etat_membre_id,
                'rubrique_id' => $prevision->rubrique_id,
                'montant_prevu' => $prevision->montant_retenu,
                'montant_realise' => $contribution->montant_realise,
                'statut' => $this->statutRecettePourMontants(
                    (float) $contribution->montant_realise,
                    (float) $prevision->montant_retenu
                ),
                'observations' => 'Reprise automatique depuis contributions (phase R1).',
            ]
        );
    }

    private function categoriePourContribution(Contribution $contribution): CategorieRecette
    {
        $code = $contribution->etat_membre_id ? 'CAT-EM' : 'CAT-PTF';

        return CategorieRecette::firstWhere('code', $code)
            ?? $this->assurerCategories()[$code];
    }

    private function referencePourContribution(Contribution $contribution): string
    {
        if ($contribution->etatMembre) {
            return 'PREV-EM-'.$contribution->etatMembre->code;
        }

        $codeSource = $contribution->sourceFinancement?->code ?? ('SRC-'.$contribution->source_financement_id);

        return 'PREV-PTF-'.$codeSource;
    }

    private function rubriquePourContribution(Contribution $contribution): ?BudgetRubrique
    {
        if ($contribution->etatMembre) {
            return BudgetRubrique::firstWhere('code', $contribution->etatMembre->code);
        }

        return BudgetRubrique::firstWhere('code', '729500');
    }

    private function statutRecettePourMontants(float $realise, float $prevu): string
    {
        if ($realise <= 0) {
            return 'prevue';
        }

        if ($realise + 0.009 >= $prevu) {
            return 'realisee';
        }

        return 'partiellement_realisee';
    }
}
