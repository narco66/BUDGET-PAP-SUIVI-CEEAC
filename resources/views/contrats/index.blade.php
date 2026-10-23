@extends('layouts.app')
@section('titre', 'Contrats')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Contrats</h4>
    @can('contrat.creer')<a href="{{ route('contrats.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau contrat</a>@endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Tous exercices</option>
        @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected(request('exercice') == $ex->id)>{{ $ex->annee }}</option>@endforeach
    </select></div>
    <div class="col-auto"><select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Tous statuts</option>
        @foreach ($statuts as $k => $v)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $v }}</option>@endforeach
    </select></div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>N°</th><th>Objet</th><th>Titulaire</th><th>Type</th><th class="text-end">Montant</th>
            <th>Marché</th><th class="text-center">Statut</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse ($contrats as $c)
            <tr>
                <td class="font-monospace small">{{ $c->numero }}</td>
                <td>{{ \Illuminate\Support\Str::limit($c->objet, 38) }}</td>
                <td class="small">{{ $c->titulaire }}</td>
                <td class="small">{{ \App\Models\Contrat::TYPES[$c->type_contrat] ?? $c->type_contrat }}</td>
                <td class="text-end">{{ number_format($c->montant, 0, ',', ' ') }}</td>
                <td class="small font-monospace">{{ $c->marche?->numero ?? '—' }}</td>
                <td class="text-center"><span class="badge bg-info-subtle text-dark">{{ \App\Models\Contrat::STATUTS[$c->statut] ?? $c->statut }}</span></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('contrats.show', $c) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a>
                    @can('contrat.modifier')<a href="{{ route('contrats.edit', $c) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>@endcan
                    @can('contrat.supprimer')<form action="{{ route('contrats.destroy', $c) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button></form>@endcan
                </td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">Aucun contrat.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $contrats->links() }}</div>
@endsection
