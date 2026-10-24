<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Recettes\GestionRecettesService;
use App\Services\Recettes\RecettesReportingService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecettesEtatsApiController extends Controller
{
    private const JEUX = ['mobilisation', 'arrieres', 'encaissements', 'previsions', 'financier'];

    public function __construct(
        private readonly RecettesReportingService $reporting,
        private readonly GestionRecettesService $gestion,
    ) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('recette.voir'), 403);

        $jeu = (string) $request->string('jeu', 'mobilisation');
        if (! in_array($jeu, self::JEUX, true)) {
            return response()->json([
                'message' => 'Jeu d’état inconnu.',
                'code' => 'jeu_invalide',
            ], 422);
        }

        $exerciceId = $request->filled('exercice') ? $request->integer('exercice') : null;
        $peutExporter = (bool) $request->user()?->can('recette.exporter');
        $prevu = $this->gestion->syntheseExercice($exerciceId ?? 0);
        $mobilisation = $this->reporting->syntheseMobilisation($exerciceId);

        return response()->json([
            'jeu' => $jeu,
            'peut_exporter' => $peutExporter,
            'synthese_previsions' => [
                'previsions' => (int) $prevu['previsions'],
                'recettes' => (int) $prevu['recettes'],
                'montant_prevu' => Montant::api($prevu['montant_prevu']),
                'montant_realise' => Montant::api($prevu['montant_realise']),
            ],
            'synthese_mobilisation' => [
                'appele' => Montant::api($mobilisation['appele']),
                'recouvre' => Montant::api($mobilisation['recouvre']),
                'arriere' => Montant::api($mobilisation['arriere']),
                'taux' => Montant::api($mobilisation['taux'], 1),
            ],
            'lignes' => $this->lignes($jeu, $exerciceId),
            'exports' => $peutExporter ? $this->exports($exerciceId) : [],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function lignes(string $jeu, ?int $exerciceId): array
    {
        return match ($jeu) {
            'mobilisation', 'financier' => $this->reporting->mobilisationParEtat($exerciceId)
                ->map(fn ($r) => [
                    'pays' => $r->pays,
                    'code' => $r->code,
                    'cle_repartition' => Montant::api($r->cle_repartition, 1),
                    'appele' => Montant::api($r->appele),
                    'recouvre' => Montant::api($r->recouvre),
                    'arriere' => Montant::api($r->arriere),
                    'taux' => Montant::api($r->taux, 1),
                ])->values()->all(),
            'arrieres' => $this->reporting->arrieres($exerciceId)->take(200)->map(fn ($a) => [
                'id' => $a->id,
                'reference' => $a->reference,
                'etat' => $a->etatMembre?->pays,
                'source' => $a->sourceFinancement?->libelle,
                'montant_appele' => Montant::api($a->montant_appele),
                'montant_regle' => Montant::api($a->montant_regle),
                'solde' => Montant::api($a->solde),
                'penalite' => Montant::api($a->penalite),
                'date_echeance' => $a->date_echeance?->toDateString(),
                'en_retard' => $a->enRetard(),
            ])->values()->all(),
            'encaissements' => $this->reporting->encaissements($exerciceId)->take(200)->map(fn ($e) => [
                'id' => $e->id,
                'reference' => $e->reference,
                'statut' => $e->statut,
                'montant_xaf' => Montant::api($e->montant_xaf),
                'date_encaissement' => $e->date_encaissement?->toDateString(),
                'recette' => $e->recette?->reference,
                'categorie' => $e->recette?->categorie?->libelle,
                'etat_membre' => $e->recette?->etatMembre?->pays,
            ])->values()->all(),
            'previsions' => $this->reporting->previsions($exerciceId)->take(200)->map(fn ($p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'statut' => $p->statut,
                'categorie' => $p->categorie?->libelle,
                'etat_membre' => $p->etatMembre?->pays,
                'montant_prevu' => Montant::api($p->montant_prevu),
                'montant_revise' => $p->montant_revise === null ? null : Montant::api($p->montant_revise),
                'montant_retenu' => Montant::api($p->montant_retenu),
            ])->values()->all(),
            default => [],
        };
    }

    /** @return array<string, list<array{format: string, href: string}>> */
    private function exports(?int $exerciceId): array
    {
        $q = $exerciceId ? ['exercice' => $exerciceId] : [];
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        $href = function (string $name, array $params = []) use ($legacy, $q): string {
            return $legacy.route($name, array_merge($q, $params), false);
        };

        return [
            'financier' => [
                ['format' => 'pdf', 'href' => $href('recettes.rapports.financier.pdf')],
                ['format' => 'word', 'href' => $href('recettes.rapports.financier.word')],
                ['format' => 'archiver', 'href' => $href('recettes.rapports.archiver', ['cle' => 'rapport-financier', 'format' => 'pdf'])],
            ],
            'mobilisation' => [
                ['format' => 'pdf', 'href' => $href('recettes.rapports.mobilisation.pdf')],
                ['format' => 'excel', 'href' => $href('recettes.rapports.mobilisation.excel')],
                ['format' => 'csv', 'href' => $href('recettes.rapports.mobilisation.csv')],
                ['format' => 'archiver', 'href' => $href('recettes.rapports.archiver', ['cle' => 'mobilisation', 'format' => 'pdf'])],
            ],
            'arrieres' => [
                ['format' => 'pdf', 'href' => $href('recettes.rapports.arrieres.pdf')],
                ['format' => 'excel', 'href' => $href('recettes.rapports.arrieres.excel')],
                ['format' => 'archiver', 'href' => $href('recettes.rapports.archiver', ['cle' => 'arrieres', 'format' => 'pdf'])],
            ],
            'encaissements' => [
                ['format' => 'excel', 'href' => $href('recettes.rapports.encaissements.excel')],
                ['format' => 'csv', 'href' => $href('recettes.rapports.encaissements.csv')],
                ['format' => 'archiver', 'href' => $href('recettes.rapports.archiver', ['cle' => 'encaissements', 'format' => 'excel'])],
            ],
            'previsions' => [
                ['format' => 'excel', 'href' => $href('recettes.rapports.previsions.excel')],
                ['format' => 'csv', 'href' => $href('recettes.rapports.previsions.csv')],
                ['format' => 'archiver', 'href' => $href('recettes.rapports.archiver', ['cle' => 'previsions', 'format' => 'excel'])],
            ],
        ];
    }
}
