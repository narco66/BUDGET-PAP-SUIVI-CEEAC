<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Matrice rôles × permissions (lecture globale). */
class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('role.voir'), 403);

        $permissionsParModule = Permission::orderBy('module')->orderBy('action')->get()->groupBy('module');
        $roles = Role::with('permissions')->orderBy('name')->get();

        // Index rapide rôle->permissions pour le rendu de la matrice
        $matrice = $roles->mapWithKeys(fn ($r) => [$r->id => $r->permissions->pluck('name')->flip()]);

        return view('permissions.index', compact('permissionsParModule', 'roles', 'matrice'));
    }
}
