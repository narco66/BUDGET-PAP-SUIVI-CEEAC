@extends('layouts.app')
@section('titre', 'Matrice d\'alignement stratégique')
@section('contenu')
@include('strategie._nav', ['active' => 'matrice'])
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div><h1 class="h5 mb-0">Matrice d'alignement stratégique</h1>
    <p class="text-muted small mb-0">Taux de couverture : <strong>{{ $synthese['taux_matrice'] }}%</strong></p></div>
    @can('strategie.export')
    <div class="d-flex gap-1">
        <a href="{{ route('strategie.export.excel', ['catalogue' => 'matrice', 'plan' => $planId]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel"></i> Excel</a>
        <a href="{{ route('strategie.export.matrice.pdf', $planId ? ['plan' => $planId] : []) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> PDF</a>
    </div>
    @endcan
</div>
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4"><select name="plan" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">— Tous les plans —</option>
        @foreach($plans as $p)<option value="{{ $p->id }}" @selected($planId===$p->id)>{{ $p->reference }}</option>@endforeach
    </select></div>
</form>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
<thead class="table-light"><tr><th>Vision</th><th>Orientation</th><th>Programme</th><th>Projet</th><th>Activité</th><th>Pilier RBM</th><th class="text-end">Budget</th><th class="text-center">Aligné</th></tr></thead>
<tbody>@forelse($lignes as $l)<tr class="{{ $l['aligne'] ? '' : 'table-warning' }}">
<td><code>{{ $l['vision'] }}</code></td><td><code>{{ $l['orientation'] }}</code></td><td><code>{{ $l['programme'] }}</code></td>
<td><code>{{ $l['projet'] }}</code></td><td><code>{{ $l['activite'] }}</code></td><td><code>{{ $l['pilier_rbm'] }}</code></td>
<td class="text-end">{{ number_format($l['budget'], 0, ',', ' ') }}</td>
<td class="text-center">@if($l['aligne'])<i class="bi bi-check-circle text-success"></i>@else<i class="bi bi-exclamation-circle text-warning"></i>@endif</td>
</tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">Aucune donnée.</td></tr>@endforelse</tbody></table></div></div>
@endsection
