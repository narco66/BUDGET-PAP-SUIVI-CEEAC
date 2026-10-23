<?php

namespace App\Http\Controllers;

use App\Http\Requests\StructureRequest;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StructureController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Structure::class);

        $structures = Structure::query()
            ->with('parent')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s
                ->where('libelle', 'like', "%{$request->q}%")
                ->orWhere('code', 'like', "%{$request->q}%")))
            ->orderBy('niveau')->orderBy('ordre')->paginate(20)->withQueryString();

        return view('structures.index', compact('structures'));
    }

    public function create(): View
    {
        $this->authorize('create', Structure::class);

        return view('structures.create', $this->optionsFormulaire());
    }

    public function store(StructureRequest $request): RedirectResponse
    {
        $this->authorize('create', Structure::class);

        $data = $request->validated();
        $data['niveau'] = $this->calculerNiveau($data['parent_id'] ?? null);
        Structure::create($data);

        return $this->retour($request)->with('succes', 'Structure créée.');
    }

    public function show(Structure $structure): View
    {
        $this->authorize('view', $structure);

        $structure->load('parent', 'enfants', 'responsable', 'journaux.user');

        return view('structures.show', compact('structure'));
    }

    public function edit(Structure $structure): View
    {
        $this->authorize('update', $structure);

        return view('structures.edit', array_merge(compact('structure'), $this->optionsFormulaire($structure)));
    }

    public function update(StructureRequest $request, Structure $structure): RedirectResponse
    {
        $this->authorize('update', $structure);

        $data = $request->validated();
        $data['niveau'] = $this->calculerNiveau($data['parent_id'] ?? null);
        $structure->update($data);

        return $this->retour($request)->with('succes', 'Structure mise à jour.');
    }

    public function destroy(Structure $structure): RedirectResponse
    {
        $this->authorize('delete', $structure);

        if ($structure->enfants()->exists()) {
            return back()->with('erreur', 'Impossible : cette structure a des structures rattachées.');
        }

        $structure->delete();

        return $this->retour(request())->with('succes', 'Structure désactivée (suppression logique).');
    }

    /** Redirige vers l'organigramme ou la liste selon l'origine de l'action. */
    private function retour(Request $request): RedirectResponse
    {
        return $request->input('retour') === 'organigramme'
            ? redirect()->route('organigramme')
            : redirect()->route('structures.index');
    }

    /** @return array{parents:\Illuminate\Support\Collection, responsables:\Illuminate\Support\Collection} */
    private function optionsFormulaire(?Structure $exclure = null): array
    {
        return [
            'parents' => Structure::when($exclure, fn ($q) => $q->whereKeyNot($exclure->id))
                ->orderBy('libelle')->get(['id', 'code', 'libelle']),
            'responsables' => User::where('actif', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function calculerNiveau(?int $parentId): int
    {
        return $parentId ? (Structure::find($parentId)->niveau + 1) : 0;
    }
}
