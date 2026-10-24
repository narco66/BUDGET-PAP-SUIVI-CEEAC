<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\Rbm\RbmReferentielService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RbmVueApiController extends Controller
{
    private const JEUX = ['arbre', 'matrice', 'cadre', 'chaine', 'theorie'];

    public function __construct(
        private readonly RbmReferentielService $referentiel,
        private readonly AccessScopeService $scopes,
    ) {}

    public function hub(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('rbm.voir'), 403);
        $exercice = $this->exercice($request);
        $arbre = $this->arbreCharge($exercice, $request->user());
        $activites = $arbre->piliers->flatMap->axes->flatMap->produits->flatMap->sousProduits->flatMap->activites;

        return response()->json([
            'exercice' => $exercice->only(['id', 'annee', 'libelle', 'statut']),
            'compteurs' => [
                'piliers' => $arbre->piliers->count(),
                'axes' => $arbre->piliers->sum(fn ($p) => $p->axes->count()),
                'activites' => $activites->count(),
                'montant_total' => Montant::api($activites->sum('montant_total')),
            ],
            'peut_creer' => (bool) $request->user()?->can('rbm.creer'),
            'peut_importer' => (bool) $request->user()?->can('rbm.importer'),
            'peut_exporter' => (bool) $request->user()?->can('rbm.exporter'),
            'exports' => $this->exports($exercice, (bool) $request->user()?->can('rbm.exporter'), (bool) $request->user()?->can('rbm.generer_pdf')),
        ]);
    }

    public function vue(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('rbm.voir'), 403);
        $jeu = (string) $request->string('jeu', 'arbre');
        if (! in_array($jeu, self::JEUX, true)) {
            return response()->json(['message' => 'Jeu RBM inconnu.', 'code' => 'jeu_invalide'], 422);
        }

        $exercice = $this->exercice($request);

        return response()->json([
            'jeu' => $jeu,
            'exercice' => $exercice->only(['id', 'annee', 'libelle', 'statut']),
            'lignes' => $this->lignes($jeu, $exercice, $request->user()),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function lignes(string $jeu, Exercice $exercice, User $user): array
    {
        return match ($jeu) {
            'arbre' => $this->aplatirArbre($this->arbreCharge($exercice, $user)),
            'matrice' => $this->referentiel->matriceRbm($exercice)->map(fn ($l) => [
                'pilier' => $l['pilier'],
                'axe' => $l['axe'],
                'produit' => $l['produit'],
                'sous_produit' => $l['sous_produit'],
                'activite' => $l['activite'],
                'intitule' => $l['intitule'],
                'montant' => Montant::api($l['montant']),
                'nb_taches' => (int) $l['nb_taches'],
                'nb_indicateurs' => (int) $l['nb_indicateurs'],
            ])->values()->all(),
            'cadre' => collect($this->referentiel->cadreLogique($exercice))->flatMap(function ($bloc) {
                return collect($bloc['objectifs'] ?? [])->map(fn ($obj) => [
                    'pilier' => $bloc['pilier']['code'] ?? null,
                    'axe' => $obj['axe']['code'] ?? null,
                    'intitule' => $obj['axe']['intitule'] ?? null,
                    'objectif_strategique' => $obj['objectif_strategique'] ?? null,
                    'commentaire' => $obj['commentaire'] ?? null,
                    'indicateurs' => collect($obj['indicateurs'] ?? [])->pluck('code')->filter()->values()->all(),
                ]);
            })->values()->all(),
            'chaine' => $this->aplatirChaine($this->referentiel->chaineResultats($exercice)),
            'theorie' => $this->aplatirTheorie($this->referentiel->theorieChangement($exercice)),
            default => [],
        };
    }

    private function arbreCharge(Exercice $exercice, User $user): Exercice
    {
        $exercice->load(['piliers.axes.produits.sousProduits.activites.taches']);
        if ($this->scopes->estGlobal($user)) {
            return $exercice;
        }

        $ids = $this->scopes->structuresAutorisees($user);
        foreach ($exercice->piliers as $pilier) {
            foreach ($pilier->axes as $axe) {
                $axe->setRelation(
                    'produits',
                    $axe->produits->filter(fn ($produit) => ! $produit->structure_id || $ids->contains($produit->structure_id))->values()
                );
                foreach ($axe->produits as $produit) {
                    foreach ($produit->sousProduits as $sousProduit) {
                        $sousProduit->setRelation(
                            'activites',
                            $sousProduit->activites->filter(fn ($activite) => ! $activite->structure_id || $ids->contains($activite->structure_id))->values()
                        );
                    }
                }
            }
        }

        return $exercice;
    }

    /** @return list<array<string, mixed>> */
    private function aplatirArbre(Exercice $exercice): array
    {
        $lignes = [];
        foreach ($exercice->piliers as $pilier) {
            $montantPilier = $pilier->axes->flatMap->produits->flatMap->sousProduits->flatMap->activites->sum('montant_total');
            $lignes[] = $this->noeud('pilier', 0, $pilier->code, $pilier->intitule, $montantPilier, $pilier->statut, $pilier->id);
            foreach ($pilier->axes as $axe) {
                $lignes[] = $this->noeud('axe', 1, $axe->code, $axe->intitule, null, $axe->statut, $axe->id);
                foreach ($axe->produits as $produit) {
                    $lignes[] = $this->noeud('produit', 2, $produit->code, $produit->intitule, null, $produit->statut, $produit->id);
                    foreach ($produit->sousProduits as $sp) {
                        $lignes[] = $this->noeud('sous_produit', 3, $sp->code, $sp->intitule, null, $sp->statut, $sp->id);
                        foreach ($sp->activites as $act) {
                            $lignes[] = $this->noeud('activite', 4, $act->code, $act->intitule, $act->montant_total, $act->statut, $act->id);
                            foreach ($act->taches as $tache) {
                                $lignes[] = $this->noeud('tache', 5, $tache->code, $tache->intitule, null, $tache->statut, $tache->id);
                            }
                        }
                    }
                }
            }
        }

        return $lignes;
    }

    /** @param  list<array<string, mixed>>  $chaine */
    private function aplatirChaine(array $chaine): array
    {
        $lignes = [];
        foreach ($chaine as $pilier) {
            $lignes[] = $this->noeud('pilier', 0, $pilier['code'] ?? '', $pilier['intitule'] ?? '', null, null);
            foreach ($pilier['enfants'] ?? [] as $axe) {
                $lignes[] = $this->noeud('axe', 1, $axe['code'] ?? '', $axe['intitule'] ?? '', null, null);
                foreach ($axe['enfants'] ?? [] as $act) {
                    $lignes[] = $this->noeud('activite', 2, $act['code'] ?? '', $act['intitule'] ?? '', $act['montant'] ?? 0, null);
                }
            }
        }

        return $lignes;
    }

    /** @param  array{piliers: list<array<string, mixed>>, hypotheses?: list<array<string, mixed>>}  $tc */
    private function aplatirTheorie(array $tc): array
    {
        $lignes = [];
        foreach ($tc['piliers'] ?? [] as $pilier) {
            $lignes[] = $this->noeud('impact', 0, $pilier['code'] ?? '', $pilier['intitule'] ?? '', null, null);
            foreach ($pilier['axes'] ?? [] as $axe) {
                $lignes[] = $this->noeud('effet', 1, $axe['code'] ?? '', $axe['intitule'] ?? '', null, null);
                foreach ($axe['produits'] ?? [] as $produit) {
                    $lignes[] = $this->noeud('produit', 2, $produit['code'] ?? '', $produit['intitule'] ?? '', null, null);
                    foreach ($produit['activites'] ?? [] as $act) {
                        $lignes[] = $this->noeud('activite', 3, $act['code'] ?? '', $act['intitule'] ?? '', $act['montant'] ?? 0, null);
                    }
                }
            }
        }

        return $lignes;
    }

    /** @return array<string, mixed> */
    private function noeud(string $niveau, int $profondeur, string $code, string $intitule, mixed $montant, ?string $statut, ?int $id = null): array
    {
        return [
            'id' => $id,
            'niveau' => $niveau,
            'profondeur' => $profondeur,
            'code' => $code,
            'intitule' => $intitule,
            'montant' => $montant === null ? null : Montant::api($montant),
            'statut' => $statut,
        ];
    }

    /** @return list<array{format: string, href: string}> */
    private function exports(Exercice $exercice, bool $exporter, bool $pdf): array
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');
        $href = fn (string $name) => $legacy.route($name, $exercice, false);
        $liens = [];
        if ($exporter) {
            $liens[] = ['format' => 'excel-matrice', 'href' => $href('rbm.export.matrice')];
            $liens[] = ['format' => 'excel-cadre', 'href' => $href('rbm.export.cadre-logique')];
        }
        if ($pdf) {
            $liens[] = ['format' => 'pdf-synthese', 'href' => $href('rbm.synthese.pdf')];
            $liens[] = ['format' => 'pdf-matrice', 'href' => $href('rbm.pdf.matrice')];
        }

        return $liens;
    }

    private function exercice(Request $request): Exercice
    {
        if ($request->filled('exercice')) {
            $opt = $request->input('exercice');
            $id = Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
            abort_unless($id, 422, 'Exercice introuvable.');

            return Exercice::findOrFail($id);
        }

        return Exercice::firstWhere('statut', 'ouvert') ?? Exercice::orderByDesc('annee')->firstOrFail();
    }
}
