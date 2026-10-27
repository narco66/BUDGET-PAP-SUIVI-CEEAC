<?php

namespace App\Services\Import\Domaines;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\ImportLigne;
use App\Models\User;
use App\Services\Import\Support\AnalyseLigne;
use App\Services\Import\Support\ChampImport;
use App\Services\Import\Support\ContexteImport;
use Illuminate\Database\Eloquent\Model;

/**
 * Nomenclature budgétaire (P1), format à plat : une rubrique par ligne avec ses parents
 * (titre → article → paragraphe → rubrique). Les niveaux absents sont créés ; un intitulé différent n'est
 * modifié qu'en mode « mise à jour » ; rien n'est jamais supprimé ni recodé.
 */
class NomenclatureDomaine implements DomaineImport
{
    private const NIVEAUX = ['titre', 'article', 'paragraphe', 'rubrique'];

    public function code(): string
    {
        return 'nomenclature';
    }

    public function libelle(): string
    {
        return 'Nomenclature budgétaire';
    }

    public function description(): string
    {
        return 'Titres, articles, paragraphes et rubriques d\'imputation (une rubrique par ligne, avec ses parents).';
    }

    public function champs(): array
    {
        return [
            new ChampImport('sens', 'Sens (depense, recette…)', false, 'texte', ['nature_budgetaire']),
            new ChampImport('titre_code', 'Code titre', true, 'code', ['titre']),
            new ChampImport('titre_intitule', 'Intitulé titre', false, 'texte', ['libelle_titre']),
            new ChampImport('article_code', 'Code article', true, 'code', ['article', 'chapitre']),
            new ChampImport('article_intitule', 'Intitulé article', false, 'texte', ['libelle_article']),
            new ChampImport('paragraphe_code', 'Code paragraphe', true, 'code', ['paragraphe']),
            new ChampImport('paragraphe_intitule', 'Intitulé paragraphe', false, 'texte', ['libelle_paragraphe']),
            new ChampImport('rubrique_code', 'Code rubrique', true, 'code', ['rubrique', 'imputation', 'code_imputation']),
            new ChampImport('rubrique_intitule', 'Intitulé rubrique', true, 'texte', ['libelle_rubrique', 'libelle']),
        ];
    }

    public function exigeExercice(): bool
    {
        return false;
    }

    public function modes(): array
    {
        return ['insertion', 'mise_a_jour', 'simulation'];
    }

    public function circuit(): ?string
    {
        return 'IMPORT_BUDGET';
    }

    public function autorise(User $user): bool
    {
        return $user->can('nomenclature.creer');
    }

    public function exemple(): array
    {
        return [
            ['sens' => 'depense', 'titre_code' => '6', 'titre_intitule' => 'Charges', 'article_code' => '61', 'article_intitule' => 'Services extérieurs',
                'paragraphe_code' => '614', 'paragraphe_intitule' => 'Entretien et réparations', 'rubrique_code' => '614300', 'rubrique_intitule' => 'Maintenance et réparation'],
        ];
    }

    public function preparer(ContexteImport $ctx): void
    {
        $ctx->cache['titres'] = BudgetTitre::query()->get()->keyBy(fn ($t) => $t->sens.'|'.$t->code);
        $ctx->cache['articles'] = BudgetArticle::query()->get()->keyBy(fn ($a) => $a->titre_id.'|'.$a->code);
        $ctx->cache['paragraphes'] = BudgetParagraphe::query()->get()->keyBy(fn ($p) => $p->article_id.'|'.$p->code);
        $ctx->cache['rubriques'] = BudgetRubrique::query()->get();
        $ctx->cache['rubriques_par_parent'] = $ctx->cache['rubriques']->keyBy(fn ($r) => $r->paragraphe_id.'|'.$r->code);
    }

    public function prevoir(array $lignes, ContexteImport $ctx): void
    {
    }

    public function analyser(AnalyseLigne $ligne, int $numero, ContexteImport $ctx): void
    {
        $d = $ligne->donnees;
        $sens = mb_strtolower((string) ($d['sens'] ?? 'depense'));
        if (! array_key_exists($sens, BudgetTitre::SENS)) {
            $ligne->bloquante('valeur_inconnue', "Sens « {$sens} » inconnu.", 'sens', $d['sens'] ?? null, 'Sens admis : '.implode(', ', array_keys(BudgetTitre::SENS)).'.');

            return;
        }
        $ligne->donnees['sens'] = $sens;
        $ligne->cle = implode('|', [$sens, $d['titre_code'], $d['article_code'], $d['paragraphe_code'], $d['rubrique_code']]);

        // Descente dans la hiérarchie existante ; chaque niveau absent sera créé.
        $titre = $ctx->cache['titres'][$sens.'|'.$d['titre_code']] ?? null;
        $article = $titre ? ($ctx->cache['articles'][$titre->id.'|'.$d['article_code']] ?? null) : null;
        $paragraphe = $article ? ($ctx->cache['paragraphes'][$article->id.'|'.$d['paragraphe_code']] ?? null) : null;
        $rubrique = $paragraphe ? ($ctx->cache['rubriques_par_parent'][$paragraphe->id.'|'.$d['rubrique_code']] ?? null) : null;

        $homonymes = $ctx->cache['rubriques']->where('code', $d['rubrique_code'])->reject(fn ($r) => $rubrique && $r->id === $rubrique->id);
        if ($homonymes->isNotEmpty()) {
            $ligne->avertissement('code_ambigu', "Le code rubrique « {$d['rubrique_code']} » existe déjà sous un autre paragraphe : les imputations devront préciser le paragraphe.", 'rubrique_code', $d['rubrique_code']);
        }

        foreach (['titre' => $titre, 'article' => $article, 'paragraphe' => $paragraphe, 'rubrique' => $rubrique] as $niveau => $existant) {
            $intitule = $d[$niveau.'_intitule'] ?? null;
            if ($existant && $intitule !== null && $intitule !== $existant->intitule) {
                $ligne->difference($niveau.'_intitule', $existant->intitule, $intitule);
            }
        }

        if (! $rubrique) {
            $ligne->operation = 'inserer';

            return;
        }
        $ligne->cible = $rubrique;
        if ($ligne->differences === []) {
            $ligne->operation = 'identique';
        } elseif ($ctx->miseAJourAutorisee()) {
            $ligne->operation = 'mettre_a_jour';
        } else {
            $ligne->operation = 'ignorer';
            $ligne->avertissement('existe_deja', 'Rubrique existante avec un intitulé différent : non modifiée en mode « insertion ».', 'rubrique_intitule', $d['rubrique_intitule'] ?? null, 'Choisir le mode « mise à jour contrôlée » pour corriger les intitulés.');
        }
    }

    public function rangExecution(ImportLigne $ligne): int
    {
        return 1;
    }

    public function executer(ImportLigne $ligne, ContexteImport $ctx): ?Model
    {
        $d = $ligne->donnees;
        $maj = $ligne->operation === 'mettre_a_jour';
        $intitule = fn (string $niveau) => $d[$niveau.'_intitule'] ?? $d[$niveau.'_code'];

        // firstOrCreate : un niveau créé par une ligne précédente du même lot est réutilisé.
        $titre = BudgetTitre::query()->firstOrCreate(['sens' => $d['sens'], 'code' => $d['titre_code']], ['intitule' => $intitule('titre')]);
        $article = BudgetArticle::query()->firstOrCreate(['titre_id' => $titre->id, 'code' => $d['article_code']], ['intitule' => $intitule('article')]);
        $paragraphe = BudgetParagraphe::query()->firstOrCreate(['article_id' => $article->id, 'code' => $d['paragraphe_code']], ['intitule' => $intitule('paragraphe')]);
        $rubrique = BudgetRubrique::query()->firstOrCreate(['paragraphe_id' => $paragraphe->id, 'code' => $d['rubrique_code']], ['intitule' => $intitule('rubrique')]);

        if ($maj) {
            foreach (['titre' => $titre, 'article' => $article, 'paragraphe' => $paragraphe, 'rubrique' => $rubrique] as $niveau => $modele) {
                if (isset($ligne->differences[$niveau.'_intitule'])) {
                    $modele->update(['intitule' => $ligne->differences[$niveau.'_intitule']['apres']]);
                }
            }
        }

        return $rubrique;
    }
}
