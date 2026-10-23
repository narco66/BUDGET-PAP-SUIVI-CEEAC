@extends('layouts.app')

@section('titre', 'Tableau de pilotage décisionnel')

@section('contenu')

@include('strategie._nav', ['active' => 'dashboard'])

<div class="d-flex flex-wrap justify-content-between mb-3 gap-2">

    <form method="GET" class="d-flex gap-2 align-items-center">

        <label class="small text-muted mb-0">Exercice</label>

        <select name="exercice" class="form-select form-select-sm" style="max-width:140px;" onchange="this.form.submit()">

            @foreach($exercices as $ex)<option value="{{ $ex->id }}" @selected($exerciceId==$ex->id)>{{ $ex->annee }}</option>@endforeach

        </select>

    </form>

    @can('strategie.export')

    <a href="{{ route('strategie.export.pilotage.pdf', ['exercice' => $exerciceId]) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>Export PDF</a>

    @endcan

</div>



<div class="row g-3 mb-4">

    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">Score pilotage</div><div class="fs-3 fw-bold text-primary">{{ $tableau['score_pilotage'] }}%</div>

    </div></div></div>

    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">Cohérence</div><div class="fs-5 fw-bold">{{ $tableau['coherence']['score_global'] }}%</div>

    </div></div></div>

    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">Alignement matrice</div><div class="fs-5 fw-bold">{{ $tableau['alignement']['taux_matrice'] }}%</div>

    </div></div></div>

    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">Prog. ↔ PAP</div><div class="fs-5 fw-bold">{{ $tableau['reconciliation']['taux_liaison_prog'] }}%</div>

    </div></div></div>

</div>



@if($tableau['alertes'])

<div class="alert alert-warning small"><strong>Alertes :</strong><ul class="mb-0 mt-1">@foreach($tableau['alertes'] as $a)<li>{{ $a }}</li>@endforeach</ul></div>

@endif



<div class="row g-3 mb-4">

    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Exécution PAP {{ $tableau['exercice']?->annee ?? '—' }}</div>

    <div class="card-body small">

        @if($tableau['reconciliation']['pap'])

        <p class="mb-1">Statut PAP : @include('strategie._statut', ['statut' => $tableau['reconciliation']['pap']->statut])</p>

        <ul class="mb-0">

            <li>Activités : <strong>{{ $tableau['reconciliation']['execution_pap']['activites'] ?? 0 }}</strong></li>

            <li>Taux physique : <strong>{{ $tableau['reconciliation']['execution_pap']['taux_physique'] ?? 0 }}%</strong></li>

            <li>Taux financier : <strong>{{ $tableau['reconciliation']['execution_pap']['taux_financier'] ?? 0 }}%</strong></li>

            <li>Budget prévu : <strong>{{ number_format($tableau['reconciliation']['execution_pap']['montant_prevu'] ?? 0, 0, ',', ' ') }} XAF</strong></li>

        </ul>

        @else<p class="text-muted mb-0">Aucun PAP pour cet exercice.</p>@endif

    </div></div></div>

    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Chaîne stratégique → opérationnelle</div>

    <div class="card-body small">

        <ul class="mb-0">

            <li>Piliers orientés : <strong>{{ $tableau['reconciliation']['piliers_orientes_pct'] }}%</strong></li>

            <li>Activités → projet stratégique : <strong>{{ $tableau['reconciliation']['activites_projet_pct'] }}%</strong></li>

            <li>Programmes actifs : <strong>{{ $tableau['hub']['programmes'] }}</strong></li>

            <li>Projets : <strong>{{ $tableau['hub']['projets'] }}</strong></li>

            <li>Budget projets : <strong>{{ number_format($tableau['reconciliation']['budget_projets_strategiques'], 0, ',', ' ') }} XAF</strong></li>

        </ul>

        <a href="{{ route('strategie.pont-pap', ['exercice' => $exerciceId]) }}" class="btn btn-sm btn-outline-primary mt-2">Pont PAP</a>

    </div></div></div>

</div>



<div class="row g-3">

    <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white">Top programmes stratégiques</div>

    <ul class="list-group list-group-flush small">@forelse($tableau['top_programmes'] as $p)

    <li class="list-group-item d-flex justify-content-between"><span><code>{{ $p->code }}</code> {{ Str::limit($p->intitule, 35) }}</span><span>{{ number_format($p->budget_previsionnel, 0, ',', ' ') }}</span></li>

    @empty<li class="list-group-item text-muted">Aucun programme.</li>@endforelse</ul></div></div>

    <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white">Top projets stratégiques</div>

    <ul class="list-group list-group-flush small">@forelse($tableau['top_projets'] as $p)

    <li class="list-group-item d-flex justify-content-between"><span><code>{{ $p->code }}</code> {{ Str::limit($p->intitule, 35) }}</span><span>{{ number_format($p->budget_previsionnel, 0, ',', ' ') }}</span></li>

    @empty<li class="list-group-item text-muted">Aucun projet.</li>@endforelse</ul></div></div>

</div>

@endsection

