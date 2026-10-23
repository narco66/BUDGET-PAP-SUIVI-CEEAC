@extends('layouts.app')
@section('titre', 'Matrice rôles / permissions')

@section('contenu')
<p class="text-muted small">Vue de synthèse en lecture seule. Modifiez les permissions depuis l'édition d'un rôle.</p>
<div class="card border-0 shadow-sm"><div class="table-responsive" style="max-height:75vh;">
    <table class="table table-sm table-bordered align-middle mb-0" style="font-size:.8rem;">
        <thead class="table-light" style="position:sticky;top:0;z-index:1;">
            <tr>
                <th style="position:sticky;left:0;background:#f8f9fa;">Permission</th>
                @foreach ($roles as $role)<th class="text-center" title="{{ $role->libelle }}"><span style="writing-mode:vertical-rl;transform:rotate(180deg);">{{ $role->name }}</span></th>@endforeach
            </tr>
        </thead>
        <tbody>
        @foreach ($permissionsParModule as $module => $perms)
            <tr class="table-light"><td colspan="{{ $roles->count()+1 }}" class="fw-bold text-primary">{{ $module }}</td></tr>
            @foreach ($perms as $perm)
                <tr>
                    <td style="position:sticky;left:0;background:#fff;">{{ $perm->action }}</td>
                    @foreach ($roles as $role)
                        <td class="text-center">@if($matrice[$role->id]->has($perm->name))<i class="bi bi-check-lg text-success"></i>@endif</td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
</div></div>
<div class="mt-3"><a href="{{ route('roles.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour aux rôles</a></div>
@endsection
