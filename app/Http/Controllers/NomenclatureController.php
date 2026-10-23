<?php

namespace App\Http\Controllers;

use App\Models\BudgetArticle;
use App\Models\BudgetParagraphe;
use App\Models\BudgetRubrique;
use App\Models\BudgetTitre;
use App\Models\Exercice;
use App\Services\Budget\NomenclatureStatsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Gestion hiérarchique de la nomenclature budgétaire (Titre → Article → Paragraphe → Rubrique),
 * via un contrôleur générique piloté par le paramètre {niveau}. Non destructif : l'archivage est
 * un soft delete, bloqué si l'élément possède encore des enfants ou des lignes rattachées.
 */
class NomenclatureController extends Controller
{
    /**
     * Configuration de chaque niveau hiérarchique. Le libellé `cdc` est le terme normalisé du
     * cahier des charges (Chapitre → Article → Paragraphe → Ligne) affiché côté écran, tandis que
     * `label` reste le terme historique de l'application (les tables ne sont pas renommées).
     */
    private const NIVEAUX = [
        'titre' => ['model' => BudgetTitre::class, 'label' => 'Titre', 'cdc' => 'Chapitre', 'parent' => null, 'fk' => null, 'enfants' => 'articles'],
        'article' => ['model' => BudgetArticle::class, 'label' => 'Article', 'cdc' => 'Article', 'parent' => 'titre', 'fk' => 'titre_id', 'parent_model' => BudgetTitre::class, 'enfants' => 'paragraphes'],
        'paragraphe' => ['model' => BudgetParagraphe::class, 'label' => 'Paragraphe', 'cdc' => 'Paragraphe', 'parent' => 'article', 'fk' => 'article_id', 'parent_model' => BudgetArticle::class, 'enfants' => 'rubriques'],
        'rubrique' => ['model' => BudgetRubrique::class, 'label' => 'Rubrique', 'cdc' => 'Ligne', 'parent' => 'paragraphe', 'fk' => 'paragraphe_id', 'parent_model' => BudgetParagraphe::class, 'enfants' => 'lignesBudgetaires'],
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BudgetTitre::class);

        // Arborescence (éléments actifs)
        $arbre = BudgetTitre::with(['articles.paragraphes.rubriques'])
            ->when($request->filled('sens'), fn ($q) => $q->where('sens', $request->sens))
            ->orderBy('sens')->orderBy('ordre')->orderBy('code')->get();

        // Vue tabulaire : rubriques avec chemin complet (recherche / filtres / archives)
        $archives = $request->boolean('archives');
        $rubriques = BudgetRubrique::with('paragraphe.article.titre')
            ->when($archives, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('sens'), fn ($q) => $q->whereHas('paragraphe.article.titre', fn ($t) => $t->where('sens', $request->sens)))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$request->q}%")->orWhere('intitule', 'like', "%{$request->q}%")))
            ->orderBy('code')->paginate(25)->withQueryString();

        return view('nomenclature.index', [
            'arbre' => $arbre,
            'rubriques' => $rubriques,
            'sensListe' => BudgetTitre::SENS,
            'statutListe' => BudgetTitre::STATUTS,
            'archives' => $archives,
        ]);
    }

    public function show(Request $request, string $niveau, int $id): View
    {
        $cfg = $this->cfg($niveau);
        $element = $cfg['model']::withTrashed()->findOrFail($id);
        $this->authorize('view', $element);
        $element->loadMissing($this->chaineParents($niveau));
        if ($cfg['enfants']) {
            $element->loadMissing($cfg['enfants']);
        }

        return view('nomenclature.show', compact('niveau', 'element') + ['label' => $cfg['cdc']]);
    }

    public function create(Request $request, string $niveau): View
    {
        $cfg = $this->cfg($niveau);
        $this->authorize('create', $cfg['model']);

        return view('nomenclature.form', [
            'niveau' => $niveau, 'label' => $cfg['cdc'], 'element' => new $cfg['model'],
            'parents' => $this->parents($niveau), 'parentLabel' => $this->parentLabel($niveau),
            'parentFk' => $cfg['fk'], 'sensListe' => BudgetTitre::SENS,
        ]);
    }

    public function store(Request $request, string $niveau): RedirectResponse|JsonResponse
    {
        $cfg = $this->cfg($niveau);
        $this->authorize('create', $cfg['model']);
        // Saisie manuelle : l'élément naît « brouillon » et devra être validé (séparation des fonctions).
        $element = $cfg['model']::create($this->valider($request, $niveau) + ['statut' => 'brouillon']);

        // Saisie assistée (AJAX) : on renvoie l'élément créé + le prochain code suggéré pour
        // enchaîner immédiatement une nouvelle saisie sans recharger la page.
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'element' => ['id' => $element->id, 'code' => $element->code, 'intitule' => $element->intitule],
                'prochain_code' => $this->prochainCode($niveau, $request->integer($cfg['fk'] ?? '') ?: null, $request->input('sens')),
            ]);
        }

        return redirect()->route('nomenclature.index')->with('succes', $cfg['cdc'].' créé(e).');
    }

    public function edit(Request $request, string $niveau, int $id): View
    {
        $cfg = $this->cfg($niveau);
        $element = $cfg['model']::findOrFail($id);
        $this->authorize('update', $element);

        return view('nomenclature.form', [
            'niveau' => $niveau, 'label' => $cfg['cdc'], 'element' => $element,
            'parents' => $this->parents($niveau), 'parentLabel' => $this->parentLabel($niveau),
            'parentFk' => $cfg['fk'], 'sensListe' => BudgetTitre::SENS,
        ]);
    }

    public function update(Request $request, string $niveau, int $id): RedirectResponse
    {
        $cfg = $this->cfg($niveau);
        $element = $cfg['model']::findOrFail($id);
        $this->authorize('update', $element);
        $element->update($this->valider($request, $niveau, $id));

        return redirect()->route('nomenclature.index')->with('succes', $cfg['cdc'].' mis(e) à jour.');
    }

    /** Archivage logique (soft delete) — refusé si des enfants/lignes sont encore rattachés. */
    public function archiver(Request $request, string $niveau, int $id): RedirectResponse
    {
        $cfg = $this->cfg($niveau);
        $element = $cfg['model']::findOrFail($id);
        $this->authorize('delete', $element);

        if ($cfg['enfants'] && $element->{$cfg['enfants']}()->exists()) {
            return back()->with('erreur', "Archivage impossible : des éléments rattachés existent encore.");
        }
        $element->delete();

        return back()->with('succes', $cfg['cdc'].' archivé(e) (suppression logique).');
    }

    public function restaurer(Request $request, string $niveau, int $id): RedirectResponse
    {
        $cfg = $this->cfg($niveau);
        $element = $cfg['model']::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $element);
        $element->restore();

        return back()->with('succes', $cfg['cdc'].' restauré(e).');
    }

    /** Validation du workflow nomenclature (brouillon → validé). Droit dédié `nomenclature.valider`. */
    public function validerElement(Request $request, string $niveau, int $id): RedirectResponse
    {
        $cfg = $this->cfg($niveau);
        $element = $cfg['model']::findOrFail($id);
        $this->authorize('validate', $element);
        $element->update(['statut' => 'valide']);

        return back()->with('succes', $cfg['cdc'].' validé(e).');
    }

    /** Tableau de bord nomenclature (L3) : volumétrie + usage des lignes (utilisées/inutilisées/sollicitées). */
    public function tableauBord(Request $request, NomenclatureStatsService $stats): View
    {
        $this->authorize('viewAny', BudgetTitre::class);

        $exercices = Exercice::orderByDesc('annee')->get();
        $exercice = Exercice::find($request->integer('exercice'))
            ?? $exercices->firstWhere('statut', 'ouvert')
            ?? $exercices->first();
        $exerciceId = $exercice?->id ?? 0;

        return view('nomenclature.tableau', [
            'exercices' => $exercices,
            'exercice' => $exercice,
            'comptes' => $stats->comptes(),
            'usage' => $stats->usageLignes($exerciceId),
            'inutilisees' => $stats->lignesInutilisees($exerciceId),
            'sollicitees' => $stats->lignesPlusSollicitees($exerciceId),
            'sensListe' => BudgetTitre::SENS,
        ]);
    }

    // ---- Saisie assistée ligne par ligne (L2) -----------------------------------------------

    /** Écran de saisie rapide : niveau + parent → code suggéré + contrôle d'unicité en direct. */
    public function assistant(Request $request): View
    {
        $this->authorize('create', BudgetTitre::class);

        $libelles = array_map(fn ($c) => ['label' => $c['label'], 'cdc' => $c['cdc'], 'fk' => $c['fk']], self::NIVEAUX);

        return view('nomenclature.assistant', [
            'niveaux' => $libelles,
            'sensListe' => BudgetTitre::SENS,
            'chapitres' => BudgetTitre::orderBy('sens')->orderBy('code')->get(['id', 'code', 'intitule', 'sens']),
            'articles' => BudgetArticle::orderBy('code')->get(['id', 'code', 'intitule', 'titre_id']),
            'paragraphes' => BudgetParagraphe::orderBy('code')->get(['id', 'code', 'intitule', 'article_id']),
        ]);
    }

    /** AJAX : contexte d'un (niveau, parent) → chemin parent, code suivant suggéré, frères existants. */
    public function contexte(Request $request): JsonResponse
    {
        $this->authorize('create', BudgetTitre::class);
        $niveau = (string) $request->query('niveau');
        $cfg = $this->cfg($niveau);
        $parentId = $request->integer('parent_id') ?: null;
        $sens = $request->query('sens');

        $chemin = null;
        $freres = collect();
        if ($niveau === 'titre') {
            if (! $sens) {
                return response()->json(['prochain_code' => '', 'chemin' => null, 'freres' => []]);
            }
            $freres = $cfg['model']::where('sens', $sens)->orderBy('code')->get(['code', 'intitule']);
        } else {
            $parent = $cfg['parent_model']::find($parentId);
            if (! $parent) {
                return response()->json(['prochain_code' => '', 'chemin' => null, 'freres' => []]);
            }
            $chemin = trim("{$parent->code} · {$parent->intitule}");
            $freres = $cfg['model']::where($cfg['fk'], $parentId)->orderBy('code')->get(['code', 'intitule']);
        }

        return response()->json([
            'prochain_code' => $this->prochainCode($niveau, $parentId, $sens),
            'chemin' => $chemin,
            'freres' => $freres,
        ]);
    }

    /** AJAX : un code est-il disponible dans le périmètre du parent (soft-deletes inclus) ? */
    public function verifier(Request $request): JsonResponse
    {
        $this->authorize('create', BudgetTitre::class);
        $niveau = (string) $request->query('niveau');
        $cfg = $this->cfg($niveau);
        $code = trim((string) $request->query('code'));
        if ($code === '') {
            return response()->json(['disponible' => false, 'message' => 'Code requis.']);
        }

        $query = $cfg['model']::withTrashed()->where('code', $code);
        if ($niveau === 'titre') {
            $query->where('sens', $request->query('sens'));
        } else {
            $query->where($cfg['fk'], $request->integer('parent_id'));
        }
        $existe = $query->exists();

        return response()->json([
            'disponible' => ! $existe,
            'message' => $existe ? 'Code déjà utilisé dans ce périmètre.' : 'Code disponible.',
        ]);
    }

    /**
     * Suggère le prochain code libre dans le périmètre du parent. Heuristique conforme à la pratique
     * budgétaire (le code enfant prolonge le code parent : 60 → 601 → 6011 → 60111). À défaut de
     * frère exploitable, propose « code parent + 1 ». Les soft-deletes sont pris en compte (l'index
     * d'unicité n'exclut pas les éléments archivés).
     */
    private function prochainCode(string $niveau, ?int $parentId, ?string $sens): string
    {
        $cfg = $this->cfg($niveau);

        if ($niveau === 'titre') {
            if (! $sens) {
                return '';
            }
            $codes = $cfg['model']::withTrashed()->where('sens', $sens)->pluck('code')->all();

            return $this->genererCodeSuivant('', $codes);
        }

        $parent = $cfg['parent_model']::find($parentId);
        if (! $parent) {
            return '';
        }
        $codes = $cfg['model']::withTrashed()->where($cfg['fk'], $parentId)->pluck('code')->all();

        return $this->genererCodeSuivant((string) $parent->code, $codes);
    }

    /** Calcule l'incrément à partir du préfixe parent et des codes frères existants. */
    private function genererCodeSuivant(string $prefixe, array $codes): string
    {
        $suffixes = [];
        $largeur = 1;
        foreach ($codes as $code) {
            $code = (string) $code;
            if ($prefixe === '') {
                if (ctype_digit($code)) {
                    $suffixes[] = (int) $code;
                    $largeur = max($largeur, strlen($code));
                }
            } elseif (str_starts_with($code, $prefixe)) {
                $suffixe = substr($code, strlen($prefixe));
                if ($suffixe !== '' && ctype_digit($suffixe)) {
                    $suffixes[] = (int) $suffixe;
                    $largeur = max($largeur, strlen($suffixe));
                }
            }
        }

        if ($suffixes === []) {
            // Aucun frère numérique exploitable : on amorce à « parent + 1 » (vide pour un titre non numérique).
            return $prefixe === '' ? '' : $prefixe.'1';
        }

        $suivant = max($suffixes) + 1;

        return $prefixe.str_pad((string) $suivant, $largeur, '0', STR_PAD_LEFT);
    }

    // ---- Internes ---------------------------------------------------------------------------

    private function cfg(string $niveau): array
    {
        abort_unless(isset(self::NIVEAUX[$niveau]), 404);

        return self::NIVEAUX[$niveau];
    }

    /** Règles de validation (unicité du code dans le périmètre du parent + cohérence hiérarchique). */
    private function valider(Request $request, string $niveau, ?int $id = null): array
    {
        $cfg = $this->cfg($niveau);
        $table = (new $cfg['model'])->getTable();

        $regles = [
            'code' => ['required', 'string', 'max:30'],
            'intitule' => ['required', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer', 'min:0'],
        ];

        if ($niveau === 'titre') {
            $regles['sens'] = ['required', Rule::in(array_keys(BudgetTitre::SENS))];
            $regles['code'][] = Rule::unique($table, 'code')->where('sens', $request->sens)->ignore($id);
        } else {
            $regles[$cfg['fk']] = ['required', Rule::exists((new $cfg['parent_model'])->getTable(), 'id')];
            $regles['code'][] = Rule::unique($table, 'code')->where($cfg['fk'], $request->input($cfg['fk']))->ignore($id);
        }

        return $request->validate($regles);
    }

    /** Options de parent disponibles (avec chemin lisible). */
    private function parents(string $niveau): array
    {
        return match ($niveau) {
            'article' => BudgetTitre::orderBy('sens')->orderBy('code')->get()
                ->mapWithKeys(fn ($t) => [$t->id => "[{$t->sens}] {$t->code} · {$t->intitule}"])->all(),
            'paragraphe' => BudgetArticle::with('titre')->orderBy('code')->get()
                ->mapWithKeys(fn ($a) => [$a->id => "{$a->titre?->code}/{$a->code} · {$a->intitule}"])->all(),
            'rubrique' => BudgetParagraphe::with('article')->orderBy('code')->get()
                ->mapWithKeys(fn ($p) => [$p->id => "{$p->article?->code}/{$p->code} · {$p->intitule}"])->all(),
            default => [],
        };
    }

    private function parentLabel(string $niveau): ?string
    {
        $parent = $this->cfg($niveau)['parent'];

        return $parent ? self::NIVEAUX[$parent]['cdc'] : null;
    }

    /** Relations parentes à charger pour l'affichage du chemin. */
    private function chaineParents(string $niveau): array
    {
        return match ($niveau) {
            'rubrique' => ['paragraphe.article.titre'],
            'paragraphe' => ['article.titre'],
            'article' => ['titre'],
            default => [],
        };
    }
}
