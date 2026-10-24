<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Services\Budget\BudgetReportService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Synthèse cycle B0–B4 (B5) — session SPA. PDF/Excel restent Blade. */
class BudgetRapportApiController extends Controller
{
    public function __construct(private readonly BudgetReportService $rapport) {}

    public function show(Request $request, Budget $budget): JsonResponse
    {
        abort_unless($request->user()?->can('budget.voir') || $request->user()?->can('ligne_budgetaire.voir'), 403);

        $brut = $this->rapport->synthese($budget->exercice_id);
        if (isset($brut['erreur'])) {
            return response()->json(['message' => $brut['erreur'], 'code' => 'exercice_absent'], 422);
        }

        $peutExporter = (bool) $request->user()?->can('rapport.voir');
        $exerciceId = (int) ($brut['exercice']['id'] ?? $budget->exercice_id);

        return response()->json([
            'budget' => (new BudgetResource($budget->load(['exercice', 'structure', 'responsable'])))->resolve($request),
            'synthese' => $this->serialiser($brut),
            'peut_exporter' => $peutExporter,
            'exports' => $peutExporter ? $this->exports($exerciceId) : [],
        ]);
    }

    /** @param  array<string, mixed>  $s */
    private function serialiser(array $s): array
    {
        $env = $s['enveloppe'] ?? null;
        if (is_array($env)) {
            $env['montant'] = Montant::api($env['montant_total'] ?? 0);
            $env['montant_total'] = Montant::api($env['montant_total'] ?? 0);
        }

        $montants = Montant::champs($s['montants'] ?? [], ['ouvert', 'revise', 'engage', 'paye']);
        $prep = $s['preparation'] ?? [];
        $prep = Montant::champs($prep, ['montant_demande', 'montant_arbitre']);

        $pluri = $s['pluriannuel'] ?? null;
        if (is_array($pluri)) {
            $pluri = Montant::champs($pluri, ['montant_budget', 'enveloppe_pluriannuelle', 'ecart_absolu', 'ecart_pct']);
            if (isset($pluri['programmation_annuelle']['ressources_prevues'])) {
                $pluri['programmation_annuelle']['ressources_prevues'] = Montant::api(
                    $pluri['programmation_annuelle']['ressources_prevues']
                );
            }
        }

        return [
            'genere_le' => $s['genere_le'] ?? null,
            'exercice' => $s['exercice'] ?? null,
            'enveloppe' => $env,
            'montants' => $montants,
            'preparation' => $prep,
            'consolidations' => $s['consolidations'] ?? ['count' => 0, 'derniere_version' => 0],
            'simulations' => $s['simulations'] ?? ['count' => 0, 'retenue' => null],
            'revisions' => $s['revisions'] ?? ['count' => 0, 'rectificatifs' => 0, 'virements' => 0],
            'pluriannuel' => $pluri,
            'audit' => $s['audit'] ?? ['anomalies_total' => 0],
            'conformite' => $s['conformite'] ?? ['score_pct' => 0, 'checks' => []],
        ];
    }

    /** @return list<array{format: string, href: string}> */
    private function exports(int $exerciceId): array
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');
        $href = function (string $format) use ($legacy, $exerciceId): string {
            return $legacy.route('reporting.generer', [
                'type' => 'budget_institutionnel',
                'format' => $format,
                'exercice' => $exerciceId,
            ], false);
        };

        return [
            ['format' => 'pdf', 'href' => $href('pdf')],
            ['format' => 'excel', 'href' => $href('excel')],
        ];
    }
}
