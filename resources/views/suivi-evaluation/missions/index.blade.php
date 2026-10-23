@extends('layouts.app')
@section('titre', 'Missions S&E')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div><h1 class="h4 mb-0">Missions de suivi-évaluation</h1></div>
    <div class="d-flex gap-2">
        @if($exercice)<form method="GET"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">@foreach($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>{{ $ex->annee }}</option>@endforeach</select></form>@endif
        @canany(['suivi_evaluation.create','suivi.creer'])<a href="{{ route('suivi-evaluation.missions.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle mission</a>@endcanany
    </div>
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>N°</th><th>Libellé</th><th>Type</th><th>Statut</th><th>Score</th><th></th></tr></thead>
    <tbody>@forelse($missions as $m)<tr>
        <td><code>{{ $m->numero }}</code></td><td>{{ $m->libelle }}</td>
        <td>{{ $types[$m->type] ?? $m->type }}</td>
        <td>{{ $statuts[$m->statut] ?? $m->statut }}</td>
        <td>{{ $m->score_mission !== null ? $m->score_mission.'%' : '—' }}</td>
        <td><a href="{{ route('suivi-evaluation.missions.show', $m) }}" class="btn btn-sm btn-link">Voir</a></td>
    </tr>@empty<tr><td colspan="6" class="text-muted text-center py-4">Aucune mission.</td></tr>@endforelse</tbody>
</table></div>{{ $missions->links() }}</div>
@endsection
