<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);
        $roles = Role::withCount('permissions')->orderBy('name')->get();

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('roles.create', $this->matrice());
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);
        $data = $request->validated();

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->forceFill(['type' => $data['type'], 'libelle' => $data['libelle'], 'description' => $data['description'] ?? null])->save();
        $role->syncPermissions($data['permissions'] ?? []);
        AuditLogger::enregistrer('role_cree', $role, null, ['name' => $role->name]);

        return redirect()->route('roles.index')->with('succes', 'Rôle créé.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('roles.edit', array_merge(['role' => $role], $this->matrice()));
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);
        $data = $request->validated();
        $avant = $role->permissions->pluck('name')->all();

        // Le code (name) d'un rôle système n'est pas renommable.
        if ($role->type !== 'systeme') {
            $role->name = $data['name'];
        }
        $role->forceFill(['libelle' => $data['libelle'], 'type' => $data['type'], 'description' => $data['description'] ?? null])->save();

        $this->authorize('gererPermissions', $role);
        $role->syncPermissions($data['permissions'] ?? []);

        AuditLogger::enregistrer('role_modifie', $role, ['permissions' => $avant], ['permissions' => $data['permissions'] ?? []]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('roles.index')->with('succes', 'Rôle mis à jour.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->protege || $role->type === 'systeme') {
            return back()->with('erreur', 'Ce rôle est protégé et ne peut pas être supprimé.');
        }

        if ($role->users()->exists()) {
            return back()->with('erreur', "Impossible : ce rôle est attribué à des utilisateurs.");
        }

        AuditLogger::enregistrer('role_supprime', $role, ['name' => $role->name], null);
        $role->delete();

        return redirect()->route('roles.index')->with('succes', 'Rôle supprimé.');
    }

    /** Permissions groupées par module pour la matrice. */
    private function matrice(): array
    {
        return ['permissionsParModule' => Permission::orderBy('module')->orderBy('action')->get()->groupBy('module')];
    }
}
