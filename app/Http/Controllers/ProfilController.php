<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilRequest;
use App\Models\Profil;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class ProfilController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Profil::class);
        $profils = Profil::with('roles')->withCount('utilisateurs')->orderBy('libelle')->paginate(20);

        return view('profils.index', compact('profils'));
    }

    public function create(): View
    {
        $this->authorize('create', Profil::class);

        return view('profils.create', ['roles' => Role::orderBy('name')->get(['id', 'name', 'libelle'])]);
    }

    public function store(ProfilRequest $request): RedirectResponse
    {
        $this->authorize('create', Profil::class);
        $data = $request->validated();
        $profil = Profil::create($data);
        $profil->roles()->sync($data['roles'] ?? []);

        return redirect()->route('profils.index')->with('succes', 'Profil créé.');
    }

    public function edit(Profil $profil): View
    {
        $this->authorize('update', $profil);
        $profil->load('roles');

        return view('profils.edit', ['profil' => $profil, 'roles' => Role::orderBy('name')->get(['id', 'name', 'libelle'])]);
    }

    public function update(ProfilRequest $request, Profil $profil): RedirectResponse
    {
        $this->authorize('update', $profil);
        $data = $request->validated();
        $profil->update($data);
        $profil->roles()->sync($data['roles'] ?? []);

        return redirect()->route('profils.index')->with('succes', 'Profil mis à jour.');
    }

    public function destroy(Profil $profil): RedirectResponse
    {
        $this->authorize('delete', $profil);

        if ($profil->utilisateurs()->exists()) {
            return back()->with('erreur', 'Impossible : ce profil est attribué à des utilisateurs.');
        }

        $profil->delete();

        return redirect()->route('profils.index')->with('succes', 'Profil supprimé.');
    }
}
