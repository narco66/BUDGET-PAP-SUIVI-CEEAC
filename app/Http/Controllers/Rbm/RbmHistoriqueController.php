<?php

namespace App\Http\Controllers\Rbm;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Rbm\Niveaux;
use App\Services\Rbm\RbmReferentielService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Consultation de l'historique des modifications RBM (phase 3). */
class RbmHistoriqueController extends Controller
{
    public function __construct(private readonly RbmReferentielService $service)
    {
    }

    public function index(Request $request, Exercice $exercice): View
    {
        abort_unless($request->user()->can('rbm.consulter_journal') || $request->user()->can('audit.voir'), 403);

        $historiques = $this->service->historiquesExercice($exercice, $request->input('action'));

        return view('rbm.historique.index', compact('exercice', 'historiques'));
    }

    public function noeud(Request $request, string $niveau, int $id): View
    {
        abort_unless($request->user()->can('rbm.consulter_journal') || $request->user()->can('audit.voir'), 403);
        abort_unless(Niveaux::existe($niveau), 404);

        $config = Niveaux::get($niveau);
        $noeud = $config['model']::findOrFail($id);
        $historiques = $noeud->historiques()->with('utilisateur')->paginate(20);

        return view('rbm.historique.noeud', compact('niveau', 'noeud', 'config', 'historiques'));
    }
}
