<?php

namespace App\Services\Budget;

use App\Models\Activite;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\PartenaireFinancier;
use App\Models\SourceFinancement;
use App\Models\Structure;
use RuntimeException;

/** Assistant wizard : contexte, contrôles temps réel et cohérence PAP pour la saisie de lignes. */
class StructureBudgetWizardService
{
    public function __construct(
        private readonly ExerciceService $exerciceService,
    ) {
    }

    public function codeSuggere(Exercice $exercice): string
    {
        $n = LigneBudgetaire::where('exercice_id', $exercice->id)->count() + 1;

        return sprintf('LB-%d-%04d', $exercice->annee, $n);
    }

    /** Contexte initial ou changement d'exercice / structure. */
    public function contexte(int $exerciceId, ?int $structureId = null): array
    {
        $exercice = Exercice::findOrFail($exerciceId);
        $modifiable = true;
        $messageVerrou = null;

        try {
            $this->exerciceService->assertBudgetModifiable($exerciceId);
        } catch (RuntimeException $e) {
            $modifiable = false;
            $messageVerrou = $e->getMessage();
        }

        $data = [
            'exercice' => [
                'id' => $exercice->id,
                'annee' => $exercice->annee,
                'libelle' => $exercice->libelle,
                'statut' => $exercice->statut,
                'modifiable' => $modifiable,
                'message_verrou' => $messageVerrou,
            ],
            'code_suggere' => $this->codeSuggere($exercice),
            'structure' => null,
            'partenaires' => PartenaireFinancier::where('actif', true)->orderBy('code')->get(['id', 'code', 'libelle', 'sigle']),
            'sources' => SourceFinancement::where('actif', true)->orderBy('code')->get(['id', 'code', 'libelle', 'partenaire_id']),
        ];

        if ($structureId) {
            $structure = Structure::find($structureId);
            if ($structure) {
                $data['structure'] = [
                    'id' => $structure->id,
                    'code' => $structure->code,
                    'libelle' => $structure->libelle,
                    'sigle' => $structure->sigle,
                    'type' => $structure->type,
                    'fil' => $this->filStructure($structure),
                ];
            }
        }

        return $data;
    }

    /** Activités PAP rattachables à la structure (enrichies chaîne RBM + budget). */
    public function activites(int $exerciceId, int $structureId): array
    {
        $activites = Activite::query()
            ->where('structure_id', $structureId)
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->with([
                'sousProduit.produit.axe.pilier',
                'projetStrategique',
                'ligneBudgetaire:id,code,activite_id,montant_ouvert',
            ])
            ->orderBy('code')
            ->get();

        return $activites->map(function (Activite $a) use ($exerciceId) {
            $pilier = $a->sousProduit?->produit?->axe?->pilier;
            $sommeLignes = (float) LigneBudgetaire::where('exercice_id', $exerciceId)
                ->where('activite_id', $a->id)->whereNull('deleted_at')
                ->sum('montant_ouvert');

            return [
                'id' => $a->id,
                'code' => $a->code,
                'intitule' => $a->intitule,
                'montant_total' => (float) $a->montant_total,
                'montant_lignes' => $sommeLignes,
                'a_ligne' => $a->ligne_budgetaire_id !== null,
                'ligne_code' => $a->ligneBudgetaire?->code,
                'pilier' => $pilier ? "{$pilier->code} · {$pilier->intitule}" : null,
                'projet' => $a->projetStrategique?->intitule,
            ];
        })->values()->all();
    }

    /** Chaîne nomenclature pour une rubrique. */
    public function chaineRubrique(int $rubriqueId): ?array
    {
        $rubrique = BudgetRubrique::with('paragraphe.article.titre')->find($rubriqueId);
        if (! $rubrique) {
            return null;
        }

        $paragraphe = $rubrique->paragraphe;
        $article = $paragraphe?->article;
        $titre = $article?->titre;

        return [
            'rubrique' => ['id' => $rubrique->id, 'code' => $rubrique->code, 'intitule' => $rubrique->intitule, 'statut' => $rubrique->statut],
            'chemin' => array_filter([
                $titre ? "Titre {$titre->code} · {$titre->intitule}" : null,
                $article ? "Art. {$article->code} · {$article->intitule}" : null,
                $paragraphe ? "§ {$paragraphe->code} · {$paragraphe->intitule}" : null,
                "Rub. {$rubrique->code} · {$rubrique->intitule}",
            ]),
            'statut_ok' => $rubrique->statut === 'valide',
        ];
    }

    /**
     * Contrôles temps réel avant soumission.
     *
     * @param  array<string,mixed>  $data
     * @return array{ok: bool, erreurs: array<int,string>, alertes: array<int,string>}
     */
    public function verifier(int $exerciceId, array $data): array
    {
        $erreurs = [];
        $alertes = [];

        try {
            $this->exerciceService->assertBudgetModifiable($exerciceId);
        } catch (RuntimeException $e) {
            $erreurs[] = $e->getMessage();
        }

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $erreurs[] = 'Le code est obligatoire.';
        } elseif (LigneBudgetaire::where('exercice_id', $exerciceId)->where('code', $code)->exists()) {
            $erreurs[] = "Le code « {$code} » existe déjà pour cet exercice.";
        }

        $structureId = (int) ($data['structure_id'] ?? 0);
        $activiteId = $data['activite_id'] ?? null;
        if ($activiteId && ! Activite::where('id', $activiteId)->where('structure_id', $structureId)->exists()) {
            $erreurs[] = "L'activité n'appartient pas à la structure sélectionnée.";
        }

        $rubriqueId = (int) ($data['rubrique_id'] ?? 0);
        if ($rubrique = BudgetRubrique::find($rubriqueId)) {
            if ($rubrique->statut !== 'valide') {
                $erreurs[] = 'La rubrique sélectionnée n\'est pas validée — impossible de créer la ligne.';
            }
        }

        $montant = (float) ($data['montant_ouvert'] ?? 0);
        if ($montant <= 0) {
            $erreurs[] = 'Le montant ouvert doit être strictement positif.';
        }

        if ($activiteId && $montant > 0) {
            $activite = Activite::find($activiteId);
            if ($activite) {
                $sommeExistante = (float) LigneBudgetaire::where('exercice_id', $exerciceId)
                    ->where('activite_id', $activiteId)->whereNull('deleted_at')
                    ->sum('montant_ouvert');
                $totalApres = $sommeExistante + $montant;
                $budgetActivite = (float) $activite->montant_total;

                if ($budgetActivite > 0 && $totalApres > $budgetActivite + 0.01) {
                    $alertes[] = sprintf(
                        'Le total des lignes (%.0f XAF) dépassera le montant PAP de l\'activité (%.0f XAF).',
                        $totalApres,
                        $budgetActivite
                    );
                }

                if ($activite->ligne_budgetaire_id) {
                    $alertes[] = "L'activité {$activite->code} est déjà liée à une ligne budgétaire ; une liaison bidirectionnelle sera établie.";
                }
            }
        }

        return ['ok' => $erreurs === [], 'erreurs' => $erreurs, 'alertes' => $alertes];
    }

    /** Fil d'Ariane institutionnel (racine → structure). */
    public function filStructure(Structure $structure): array
    {
        $fil = [];
        foreach (array_reverse($structure->ancetres()) as $ancetre) {
            $fil[] = ['code' => $ancetre->code, 'libelle' => $ancetre->sigle ?: $ancetre->libelle, 'type' => $ancetre->type];
        }
        $fil[] = ['code' => $structure->code, 'libelle' => $structure->sigle ?: $structure->libelle, 'type' => $structure->type];

        return $fil;
    }
}
