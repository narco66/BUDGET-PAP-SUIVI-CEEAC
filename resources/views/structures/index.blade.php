@extends('layouts.app')
@section('titre', 'Structures')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Rechercher…" style="width:220px;">
        <select name="type" class="form-select form-select-sm" style="width:180px;" onchange="this.form.submit()">
            <option value="">Tous les types</option>
            @foreach (['presidence'=>'Présidence','vice_presidence'=>'Vice-Présidence','secretariat_general'=>'Secrétariat Général','commissariat'=>'Commissariat','departement'=>'Département','direction'=>'Direction','service'=>'Service','cellule'=>'Cellule'] as $k=>$v)
                <option value="{{ $k }}" @selected(request('type')===$k)>{{ $v }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex gap-2 align-items-center">
        @include('structures._import_buttons')
        @include('structures._export_buttons')
        @can('structure.creer')
            <a href="{{ route('structures.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle structure</a>
        @endcan
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Code</th><th>Libellé</th><th>Type</th><th>Rattachée à</th><th class="text-center">Statut</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($structures as $s)
                <tr>
                    <td><code>{{ $s->code }}</code></td>
                    <td>@for($i = 0; $i < $s->niveau; $i++)<span class="text-muted">- </span>@endfor{{ $s->libelle }} @if($s->sigle)<span class="badge bg-light text-dark">{{ $s->sigle }}</span>@endif</td>
                    <td><span class="badge bg-info-subtle text-info-emphasis">{{ str_replace('_',' ',$s->type) }}</span></td>
                    <td class="small text-muted">{{ $s->parent?->libelle ?? '—' }}</td>
                    <td class="text-center">@if($s->actif)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif</td>
                    <td class="text-end">
                        <a href="{{ route('structures.show', $s) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                        @can('structure.modifier')<a href="{{ route('structures.edit', $s) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
                        @can('structure.supprimer')
                        <form action="{{ route('structures.destroy', $s) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirmer la désactivation ?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Aucune structure.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $structures->links() }}</div>
@endsection
