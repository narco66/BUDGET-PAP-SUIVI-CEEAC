<?php

namespace App\Http\Controllers;

use App\Models\AppelFonds;
use App\Models\Encaissement;
use App\Models\Engagement;
use App\Models\ExpressionBesoin;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\PieceJustificative;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PieceController extends Controller
{
    /** Types d'objets porteurs de pièces (alias public => modèle). */
    private const TYPES = [
        'besoin' => ExpressionBesoin::class,
        'engagement' => Engagement::class,
        'liquidation' => Liquidation::class,
        'ordonnancement' => Ordonnancement::class,
        'paiement' => Paiement::class,
        'encaissement' => Encaissement::class,
        'appel' => AppelFonds::class,
    ];

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('ged.creer'), 403);
        $objet = $this->resoudre($type, $id);

        $request->validate([
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'categorie' => ['nullable', 'string', 'max:50'],
        ]);

        $fichier = $request->file('fichier');
        $chemin = $fichier->store('ged/'.now()->format('Y/m'), 'local');

        $objet->pieces()->create([
            'nom' => $fichier->getClientOriginalName(),
            'chemin' => $chemin,
            'type_mime' => $fichier->getClientMimeType(),
            'taille' => $fichier->getSize(),
            'categorie' => $request->input('categorie', 'justificatif'),
            'televerse_par' => $request->user()->id,
        ]);

        return back()->with('succes', 'Pièce justificative ajoutée.');
    }

    public function download(Request $request, PieceJustificative $piece): StreamedResponse
    {
        abort_unless($request->user()->can('ged.voir'), 403);
        abort_unless(Storage::disk('local')->exists($piece->chemin), 404);

        return Storage::disk('local')->download($piece->chemin, $piece->nom);
    }

    public function destroy(Request $request, PieceJustificative $piece): RedirectResponse
    {
        abort_unless($request->user()->can('ged.supprimer'), 403);

        Storage::disk('local')->delete($piece->chemin);
        $piece->delete();

        return back()->with('succes', 'Pièce supprimée.');
    }

    private function resoudre(string $type, int $id)
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type]::findOrFail($id);
    }
}
