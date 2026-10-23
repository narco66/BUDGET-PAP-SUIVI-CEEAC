@extends('layouts.app')

@section('titre', 'Rapport de cohérence stratégique')

@section('contenu')

@include('strategie._nav', ['active' => 'coherence'])

<div class="d-flex flex-wrap justify-content-between mb-3 gap-2">

    <form method="GET" class="d-flex gap-2">

        <select name="plan" class="form-select form-select-sm" style="max-width:240px;" onchange="this.form.submit()">

            <option value="">Tous les plans</option>

            @foreach($plans as $p)<option value="{{ $p->id }}" @selected($planId==$p->id)>{{ $p->reference }}</option>@endforeach

        </select>

    </form>

    @can('strategie.export')<a href="{{ route('strategie.export.coherence.pdf', $planId ? ['plan' => $planId] : []) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>Export PDF</a>@endcan

</div>



<div class="row g-3 mb-4">

    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">Score global</div><div class="fs-3 fw-bold text-primary">{{ $rapport['score_global'] }}%</div>

    </div></div></div>

    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">Objectifs</div><div class="fs-5 fw-bold">{{ $rapport['objectifs_total'] }}</div>

    </div></div></div>

    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">Taux matrice</div><div class="fs-5 fw-bold">{{ $rapport['alignement']['taux_matrice'] }}%</div>

    </div></div></div>

    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">Plans en validation</div><div class="fs-5 fw-bold text-warning">{{ $rapport['plans_en_validation']->count() }}</div>

    </div></div></div>

</div>



<div class="row g-3">

    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white">Objectifs sans indicateur ({{ $rapport['objectifs_sans_indicateur']->count() }})</div>

    <ul class="list-group list-group-flush small">@forelse($rapport['objectifs_sans_indicateur'] as $o)<li class="list-group-item"><a href="{{ route('strategie.objectifs.show', $o) }}">{{ $o->code }}</a> — {{ Str::limit($o->intitule, 50) }}</li>@empty<li class="list-group-item text-success">Couverture complète.</li>@endforelse</ul></div></div>

    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white">Indicateurs sans cible ({{ $rapport['indicateurs_sans_cible']->count() }})</div>

    <ul class="list-group list-group-flush small">@forelse($rapport['indicateurs_sans_cible'] as $i)<li class="list-group-item"><a href="{{ route('strategie.indicateurs.show', $i) }}">{{ $i->code }}</a> — {{ Str::limit($i->libelle, 50) }}</li>@empty<li class="list-group-item text-success">Toutes les cibles sont renseignées.</li>@endforelse</ul></div></div>

    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white">Risques sans mitigation ({{ $rapport['risques_sans_mitigation']->count() }})</div>

    <ul class="list-group list-group-flush small">@forelse($rapport['risques_sans_mitigation'] as $r)<li class="list-group-item"><a href="{{ route('strategie.risques.show', $r) }}">{{ $r->code }}</a> — {{ Str::limit($r->description, 50) }}</li>@empty<li class="list-group-item text-success">Tous les risques ont une mitigation.</li>@endforelse</ul></div></div>

    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white">Programmes sans budget ({{ $rapport['programmes_sans_budget']->count() }})</div>

    <ul class="list-group list-group-flush small">@forelse($rapport['programmes_sans_budget'] as $p)<li class="list-group-item"><a href="{{ route('strategie.programmes.show', $p) }}">{{ $p->code }}</a> — {{ Str::limit($p->intitule, 50) }}</li>@empty<li class="list-group-item text-success">Tous les programmes ont un budget.</li>@endforelse</ul></div></div>

</div>

@endsection

