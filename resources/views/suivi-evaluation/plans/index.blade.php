@extends('layouts.app')
@section('titre', 'Plans d\'amélioration')

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div><h1 class="h4 mb-0">Plans d'amélioration</h1></div>
    <div class="d-flex gap-2">
        @if($exercice)<form method="GET"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">@foreach($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>{{ $ex->annee }}</option>@endforeach</select></form>@endif
        @canany(['suivi_evaluation.create','suivi.creer'])<a href="{{ route('suivi-evaluation.plans.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Nouveau plan</a>@endcanany
    </div>
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>N°</th><th>Intitulé</th><th>Statut</th><th>Taux</th><th></th></tr></thead>
    <tbody>@forelse($plans as $p)<tr>
        <td><code>{{ $p->numero }}</code></td><td>{{ $p->intitule }}</td>
        <td>{{ $statuts[$p->statut] ?? $p->statut }}</td><td>{{ $p->taux_realisation }}%</td>
        <td><a href="{{ route('suivi-evaluation.plans.show', $p) }}" class="btn btn-sm btn-link">Voir</a></td>
    </tr>@empty<tr><td colspan="5" class="text-muted text-center py-4">Aucun plan.</td></tr>@endforelse</tbody>
</table></div>{{ $plans->links() }}</div>
@endsection
