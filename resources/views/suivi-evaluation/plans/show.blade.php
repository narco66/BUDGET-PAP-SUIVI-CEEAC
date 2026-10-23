@extends('layouts.app')
@section('titre', $plan->numero)

@section('contenu')
<a href="{{ route('suivi-evaluation.plans.index', ['exercice' => $plan->exercice_id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Plans</a>
<h4 class="fw-bold mb-3">{{ $plan->numero }} — {{ $plan->intitule }}</h4>
<div class="row g-3 mb-3">
    <div class="col-md-8"><div class="card border-0 shadow-sm"><div class="card-body">
        <p class="text-muted">{{ $plan->constat ?? '—' }}</p>
        <span class="badge bg-secondary">{{ \App\Models\PlanAmelioration::STATUTS[$plan->statut] ?? $plan->statut }}</span>
        <span class="ms-2">Taux : <strong>{{ $plan->taux_realisation }}%</strong></span>
    </div></div></div>
</div>
<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-bold">Actions correctives</div><div class="card-body p-0">
<table class="table table-sm mb-0"><thead class="table-light"><tr><th>Libellé</th><th>Statut</th><th>Taux</th><th>Échéance</th></tr></thead>
<tbody>@forelse($plan->actions as $a)<tr><td>{{ $a->libelle }}</td><td>{{ \App\Models\ActionCorrective::STATUTS[$a->statut] ?? $a->statut }}</td><td>{{ $a->taux }}%</td><td>{{ $a->echeance?->format('d/m/Y') ?? '—' }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">Aucune action.</td></tr>@endforelse</tbody></table></div></div>
@canany(['suivi_evaluation.create','suivi.creer'])
<form method="POST" action="{{ route('suivi-evaluation.plans.actions.store', $plan) }}" class="card border-0 shadow-sm">@csrf
<div class="card-header bg-white fw-bold">Ajouter une action</div><div class="card-body row g-2">
    <div class="col-md-5"><input name="libelle" class="form-control form-control-sm" placeholder="Libellé *" required></div>
    <div class="col-md-2"><select name="statut" class="form-select form-select-sm">@foreach($actionStatuts as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-2"><input type="number" name="taux" class="form-control form-control-sm" placeholder="Taux %" min="0" max="100" value="0" required></div>
    <div class="col-md-2"><input type="date" name="echeance" class="form-control form-control-sm"></div>
    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">+</button></div>
</div></form>
@endcanany
@endsection
