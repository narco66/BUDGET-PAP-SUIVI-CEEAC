@extends('layouts.app')
@section('titre', 'Rapports institutionnels')

@section('contenu')
<form method="GET" class="mb-3">
    <select name="exercice" class="form-select form-select-sm" style="width:180px;" onchange="this.form.submit()">
        @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id===$ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
    </select>
</form>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold"><i class="bi bi-cash-stack me-1"></i>Rapport d'exécution budgétaire</h6>
            <p class="small text-muted">Crédits ouverts, engagés, liquidés, ordonnancés, payés ; par nature et par structure.</p>
            @can('rapport.exporter')
            <a href="{{ route('rapports.execution.pdf', ['exercice'=>$exercice?->id]) }}" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            <a href="{{ route('rapports.execution.excel', ['exercice'=>$exercice?->id]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
            @endcan
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold"><i class="bi bi-bullseye me-1"></i>Rapport du PAP</h6>
            <p class="small text-muted">Synthèse par pilier : activités, montants CEEAC/PTF, taux de réalisation.</p>
            @can('rapport.exporter')
            <a href="{{ route('rapports.pap.pdf', ['exercice'=>$exercice?->id]) }}" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            @endcan
        </div></div>
    </div>
</div>
@endsection
