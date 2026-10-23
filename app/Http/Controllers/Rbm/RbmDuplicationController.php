<?php

namespace App\Http\Controllers\Rbm;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Services\Rbm\RbmDuplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Duplication du référentiel RBM entre exercices (phase E8). */
class RbmDuplicationController extends Controller
{
    public function __construct(private readonly RbmDuplicationService $service)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('rbm.admin') || $request->user()->can('rbm.modifier'), 403);

        $data = $request->validate([
            'exercice_source' => ['required', 'exists:exercices,id'],
            'exercice_cible' => ['required', 'exists:exercices,id', 'different:exercice_source'],
            'forcer' => ['nullable', 'boolean'],
        ]);

        $source = Exercice::findOrFail($data['exercice_source']);
        $cible = Exercice::findOrFail($data['exercice_cible']);

        try {
            $stats = $this->service->dupliquerReferentiel(
                $source,
                $cible,
                $request->user(),
                (bool) ($data['forcer'] ?? false),
            );
        } catch (\RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()
            ->route('rbm.hub', ['exercice' => $cible->id])
            ->with('succes', sprintf(
                'Référentiel reporté : %d pilier(s), %d activité(s), %d indicateur(s).',
                $stats['piliers'],
                $stats['activites'],
                $stats['indicateurs'],
            ));
    }
}
