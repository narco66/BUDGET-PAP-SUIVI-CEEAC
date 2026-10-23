<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Ged\GedRechercheSauvegardee;
use App\Models\Rapport;
use App\Services\Ged\GedRapportBridgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GedIntegrationController extends Controller
{
    public function indexerRapport(Request $request, Rapport $rapport, GedRapportBridgeService $bridge): RedirectResponse
    {
        abort_unless($request->user()->can('ged.creer'), 403);

        $document = $bridge->indexer($rapport, $request->user());

        return redirect()->route('ged.documents.show', $document)
            ->with('succes', 'Rapport indexé dans la GED.');
    }

    public function sauvegarderRecherche(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ged.rechercher') || $request->user()->can('ged.voir'), 403);

        $request->validate([
            'nom' => ['required', 'string', 'max:120'],
        ]);

        $filtres = $request->except(['_token', 'nom', 'par_defaut']);

        if ($request->boolean('par_defaut')) {
            GedRechercheSauvegardee::where('utilisateur_id', $request->user()->id)
                ->update(['par_defaut' => false]);
        }

        GedRechercheSauvegardee::updateOrCreate(
            ['utilisateur_id' => $request->user()->id, 'nom' => $request->input('nom')],
            ['filtres' => $filtres, 'par_defaut' => $request->boolean('par_defaut')],
        );

        return back()->with('succes', 'Recherche sauvegardée.');
    }

    public function supprimerRecherche(Request $request, GedRechercheSauvegardee $recherche): RedirectResponse
    {
        abort_unless($recherche->utilisateur_id === $request->user()->id, 403);
        $recherche->delete();

        return back()->with('succes', 'Recherche supprimée.');
    }
}
