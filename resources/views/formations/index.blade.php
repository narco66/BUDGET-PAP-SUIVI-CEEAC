@extends('layouts.app')
@section('titre', 'Formations & ateliers')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Ateliers & formations</h4>
    @can('formation.creer')<a href="{{ route('formations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle session</a>@endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Tous exercices</option>
        @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected(request('exercice') == $ex->id)>{{ $ex->annee }}</option>@endforeach
    </select></div>
    <div class="col-auto"><select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Tous types</option>
        @foreach ($types as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach
    </select></div>
    <div class="col-auto"><select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Tous statuts</option>
        @foreach ($statuts as $k => $v)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $v }}</option>@endforeach
    </select></div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>N°</th><th>Intitulé</th><th>Type</th><th class="text-center">Participants</th>
            <th class="text-center">H / F</th><th class="text-end">Coût</th><th class="text-center">Statut</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse ($formations as $f)
            <tr>
                <td class="font-monospace small">{{ $f->numero }}</td>
                <td>{{ \Illuminate\Support\Str::limit($f->intitule, 40) }}</td>
                <td class="small">{{ \App\Models\Formation::TYPES[$f->type] ?? $f->type }}</td>
                <td class="text-center">{{ $f->nb_participants }}</td>
                <td class="text-center small">{{ $f->nb_hommes }} / {{ $f->nb_femmes }}</td>
                <td class="text-end">{{ number_format($f->cout, 0, ',', ' ') }}</td>
                <td class="text-center"><span class="badge bg-{{ $f->statut === 'realise' ? 'success' : ($f->statut === 'annule' ? 'danger' : 'secondary') }}">{{ \App\Models\Formation::STATUTS[$f->statut] }}</span></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('formations.show', $f) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a>
                    @can('formation.modifier')<a href="{{ route('formations.edit', $f) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>@endcan
                    @can('formation.supprimer')<form action="{{ route('formations.destroy', $f) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button></form>@endcan
                </td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">Aucune formation.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $formations->links() }}</div>
@endsection
