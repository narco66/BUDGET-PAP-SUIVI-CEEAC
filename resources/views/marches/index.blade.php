@extends('layouts.app')
@section('titre', 'Marchés publics / PPM')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Marchés publics <span class="text-muted fs-6">(Plan de Passation des Marchés)</span></h4>
    @can('marche.creer')<a href="{{ route('marches.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau marché</a>@endcan
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
            <th>N°</th><th>Objet</th><th>Mode</th><th class="text-end">Estimé</th><th class="text-end">Attribué</th>
            <th>Attributaire</th><th class="text-center">Statut</th><th class="text-center">Contrat</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse ($marches as $m)
            <tr>
                <td class="font-monospace small">{{ $m->numero }}</td>
                <td>{{ \Illuminate\Support\Str::limit($m->objet, 40) }}</td>
                <td class="small">{{ \App\Models\Marche::MODES[$m->mode_passation] ?? $m->mode_passation }}</td>
                <td class="text-end">{{ number_format($m->montant_estime, 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($m->montant_attribue, 0, ',', ' ') }}</td>
                <td class="small text-muted">{{ $m->attributaire ?? '—' }}</td>
                <td class="text-center"><span class="badge bg-info-subtle text-dark">{{ \App\Models\Marche::STATUTS[$m->statut] ?? $m->statut }}</span></td>
                <td class="text-center">@if($m->contrat)<i class="bi bi-link-45deg text-success" title="{{ $m->contrat->numero }}"></i>@else<span class="text-muted">—</span>@endif</td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('marches.show', $m) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a>
                    @can('marche.modifier')<a href="{{ route('marches.edit', $m) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>@endcan
                    @can('marche.supprimer')<form action="{{ route('marches.destroy', $m) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button></form>@endcan
                </td>
            </tr>
        @empty<tr><td colspan="9" class="text-center text-muted py-4">Aucun marché.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $marches->links() }}</div>
@endsection
