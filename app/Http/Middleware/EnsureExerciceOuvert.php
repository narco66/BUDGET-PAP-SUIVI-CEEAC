<?php

namespace App\Http\Middleware;

use App\Depense\Etapes;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use App\Services\Budget\ExerciceService;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** Bloque les écritures budgétaires/financières sur un exercice clôturé (Phase C). */
class EnsureExerciceOuvert
{
    /** Routes d'écriture soumises au contrôle (mutations uniquement). */
    private const ROUTES = [
        'budget.store', 'budget.update', 'budget.destroy',
        'budget.import.lignes', 'budget.import.pap',
        'besoins.store', 'besoins.update', 'besoins.soumettre', 'besoins.convertir', 'besoins.agir',
        'engagements.store', 'engagements.update', 'engagements.soumettre', 'engagements.agir', 'engagements.annuler',
        'depense.store', 'depense.soumettre', 'depense.agir',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (! $routeName || ! in_array($routeName, self::ROUTES, true)) {
            return $next($request);
        }

        $exerciceId = $this->resoudreExerciceId($request);
        if (! $exerciceId) {
            return $next($request);
        }

        try {
            app(ExerciceService::class)->assertExerciceOuvert($exerciceId);
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('erreur', $e->getMessage());
        }

        return $next($request);
    }

    private function resoudreExerciceId(Request $request): ?int
    {
        if ($request->filled('exercice_id')) {
            return (int) $request->input('exercice_id');
        }

        $depenseExercice = $this->resoudreDepenseExercice($request);
        if ($depenseExercice) {
            return $depenseExercice;
        }

        $ligne = $request->route('ligne');
        if ($ligne instanceof LigneBudgetaire) {
            return (int) $ligne->exercice_id;
        }

        $engagement = $request->route('engagement');
        if ($engagement instanceof Engagement) {
            return (int) $engagement->exercice_id;
        }

        $besoin = $request->route('besoin');
        if ($besoin instanceof ExpressionBesoin) {
            return (int) $besoin->exercice_id;
        }

        if ($request->filled('exercice')) {
            return (int) Exercice::find($request->input('exercice'))?->id;
        }

        return null;
    }

    private function resoudreDepenseExercice(Request $request): ?int
    {
        $type = $request->route('type');
        if (! $type || ! Etapes::existe($type)) {
            return null;
        }

        $config = Etapes::get($type);
        $id = $request->route('id');

        if ($id) {
            $item = $config['model']::find($id);
            if ($item && method_exists($item, 'engagementRacine')) {
                return (int) ($item->engagementRacine()?->exercice_id ?: 0) ?: null;
            }
        }

        if ($request->filled('parent')) {
            $parent = $config['parent_model']::find($request->integer('parent'));
            if (! $parent) {
                return null;
            }

            $engagement = match ($type) {
                'liquidation' => $parent instanceof Engagement ? $parent : null,
                'ordonnancement' => $parent->engagement ?? null,
                'paiement' => $parent->liquidation?->engagement ?? null,
                default => null,
            };

            return $engagement?->exercice_id;
        }

        return null;
    }
}
