<?php

namespace App\Http\Controllers\Strategie;

use App\Http\Controllers\Controller;
use App\Models\PieceJustificative;
use App\Models\Strategie\PlanStrategique;
use App\Models\Strategie\Vision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StrategieDocumentController extends Controller
{
    private const TYPES = [
        'plan' => PlanStrategique::class,
        'vision' => Vision::class,
    ];

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('strategie.update'), 403);
        $objet = $this->resoudre($type, $id);

        $request->validate([
            'fichier' => ['required', 'file', 'max:15360', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'categorie' => ['nullable', 'string', 'max:50'],
        ]);

        $fichier = $request->file('fichier');
        $chemin = $fichier->store('ged/strategie/'.now()->format('Y/m'), 'local');

        $objet->pieces()->create([
            'nom' => $fichier->getClientOriginalName(),
            'chemin' => $chemin,
            'type_mime' => $fichier->getClientMimeType(),
            'taille' => $fichier->getSize(),
            'categorie' => $request->input('categorie', 'document_officiel'),
            'televerse_par' => $request->user()->id,
        ]);

        return back()->with('succes', 'Document stratégique ajouté.');
    }

    public function download(Request $request, PieceJustificative $piece): StreamedResponse
    {
        abort_unless($request->user()->can('strategie.view'), 403);
        abort_unless(in_array($piece->attachable_type, array_values(self::TYPES), true), 404);
        abort_unless(Storage::disk('local')->exists($piece->chemin), 404);

        return Storage::disk('local')->download($piece->chemin, $piece->nom);
    }

    public function destroy(Request $request, PieceJustificative $piece): RedirectResponse
    {
        abort_unless($request->user()->can('strategie.update'), 403);
        abort_unless(in_array($piece->attachable_type, array_values(self::TYPES), true), 404);

        Storage::disk('local')->delete($piece->chemin);
        $piece->delete();

        return back()->with('succes', 'Document supprimé.');
    }

    private function resoudre(string $type, int $id)
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type]::findOrFail($id);
    }
}
