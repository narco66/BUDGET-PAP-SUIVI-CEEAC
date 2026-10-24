<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Structure;
use App\Models\User;
use App\Services\TableauBordService;
use App\Support\Montant;
use App\Support\PersonaTableauxBord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableauBordApiController extends Controller
{
    public function __construct(private readonly TableauBordService $tb) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('tableau_bord.voir') || $user->can('reporting.view') || $user->estAdministrateur(), 403);

        $exercice = $this->resoudreExercice($request);
        $exId = $exercice?->id ?? 0;

        $budget = $this->tb->executionBudgetaire($exId);
        $pap = $this->tb->executionPap($exId);

        $clesBudget = ['ouvert', 'alloue', 'engage', 'liquide', 'ordonnance', 'paye', 'disponible', 'reste_a_engager', 'reste_a_payer', 'revise'];
        $budgetApi = Montant::champs($budget, $clesBudget);

        foreach (['taux_engagement', 'taux_liquidation', 'taux_ordonnancement', 'taux_paiement', 'taux_execution'] as $taux) {
            if (isset($budgetApi[$taux])) {
                $budgetApi[$taux] = Montant::api($budgetApi[$taux], 2);
            }
        }

        $derniers = Engagement::query()
            ->where('exercice_id', $exId)
            ->latest('date_engagement')->latest('id')
            ->limit(6)->get()
            ->map(fn (Engagement $e) => [
                'id' => $e->id,
                'numero' => $e->numero,
                'objet' => $e->objet,
                'montant_total' => Montant::api($e->montant_total),
                'statut' => $e->statut,
                'date_engagement' => $e->date_engagement?->toDateString(),
            ]);

        return response()->json([
            'data' => [
                'exercice' => $exercice ? [
                    'id' => $exercice->id,
                    'code' => $exercice->code,
                    'annee' => $exercice->annee,
                    'libelle' => $exercice->libelle,
                    'statut' => $exercice->statut,
                    'devise' => $exercice->devise ?? 'XAF',
                ] : null,
                'persona' => PersonaTableauxBord::pour($user),
                'kpis' => [
                    'structures' => Structure::actif()->count(),
                    'utilisateurs' => User::where('actif', true)->count(),
                    'contributions' => Montant::api(Contribution::where('exercice_id', $exId)->sum('montant_appele')),
                    'credits_ouverts' => $budgetApi['ouvert'] ?? Montant::api(0),
                    'credits_engages' => $budgetApi['engage'] ?? Montant::api(0),
                    'disponible' => $budgetApi['disponible'] ?? Montant::api(0),
                ],
                'budget' => $budgetApi,
                'pap' => [
                    'activites' => (int) ($pap['activites'] ?? 0),
                    'taux_physique' => Montant::api($pap['taux_physique'] ?? 0),
                    'taux_financier' => Montant::api($pap['taux_financier'] ?? 0),
                    'taux_financier_moyen' => Montant::api($pap['taux_financier_moyen'] ?? 0),
                    'montant_prevu' => Montant::api($pap['montant_prevu'] ?? 0),
                    'montant_paye' => Montant::api($pap['montant_paye'] ?? 0),
                ],
                'par_nature' => $this->tb->consommationParNature($exId)->map(fn ($row) => Montant::champs((array) $row, ['ouvert', 'engage', 'paye'])),
                'engagements' => $this->tb->engagementsParStatut($exId)->map(fn ($row) => Montant::champs((array) $row, ['montant'])),
                'pap_par_pilier' => $this->tb->papParPilier($exId)->map(fn ($row) => Montant::champs((array) $row, ['montant', 'ceeac', 'ptf'])),
                'feux' => $this->tb->feuxIndicateurs($exId ?: null),
                'derniers_engagements' => $derniers,
            ],
        ]);
    }

    private function resoudreExercice(Request $request): ?Exercice
    {
        if ($request->filled('exercice')) {
            $valeur = $request->string('exercice');

            return Exercice::query()
                ->where('id', $valeur)
                ->orWhere('annee', $valeur)
                ->orWhere('code', $valeur)
                ->first();
        }

        return Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->first();
    }

}
