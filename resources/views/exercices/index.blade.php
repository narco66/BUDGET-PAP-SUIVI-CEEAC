@extends('layouts.app')
@section('titre', 'Exercices budgétaires')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2 flex-wrap" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Rechercher…" style="width:220px;">
        <select name="statut" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
            <option value="">Tous statuts</option>
            @foreach (\App\Models\Exercice::STATUTS as $k => $v)
                <option value="{{ $k }}" @selected(request('statut')===$k)>{{ $v }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
    @can('exercice.creer')
        <a href="{{ route('exercices.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvel exercice</a>
    @endcan
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Code</th><th>Année</th><th>Libellé</th><th>Période</th>
                    <th class="text-center">Statut</th><th class="text-center">Réf.</th><th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($exercices as $ex)
                <tr>
                    <td><code>{{ $ex->code }}</code></td>
                    <td>{{ $ex->annee }}</td>
                    <td>{{ $ex->libelle }}</td>
                    <td class="small text-muted">
                        {{ $ex->date_debut?->format('d/m/Y') }} — {{ $ex->date_fin?->format('d/m/Y') }}
                    </td>
                    <td class="text-center">
                        @if($ex->statut === 'ouvert')<span class="badge bg-success">Ouvert</span>
                        @elseif($ex->statut === 'clos')<span class="badge bg-secondary">Clôturé</span>
                        @else<span class="badge bg-warning text-dark">Brouillon</span>@endif
                    </td>
                    <td class="text-center">
                        @if($ex->est_actif)<span class="badge bg-primary">Actif</span>@else—@endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('exercices.show', $ex) }}" class="btn btn-sm btn-outline-secondary" title="Voir"><i class="bi bi-eye"></i></a>
                        @can('update', $ex)<a href="{{ route('exercices.edit', $ex) }}" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="bi bi-pencil"></i></a>@endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun exercice budgétaire.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $exercices->links() }}</div>
@endsection
