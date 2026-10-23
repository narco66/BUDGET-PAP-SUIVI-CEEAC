@extends('layouts.app')
@section('titre', 'Suivi-Évaluation')

@php
    $s = $data['scores'] ?? [];
    $niveau = $data['niveau'] ?? 'insuffisant';
    $badgeNiveau = match($niveau) {
        'satisfaisant' => 'success',
        'partiel' => 'warning',
        default => 'danger',
    };
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">Suivi-Évaluation</h1>
        <p class="text-muted small mb-0">Pilotage S&E · scores agrégés · campagnes · recommandations</p>
    </div>
    @if($exercice)
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="small text-muted mb-0">Exercice</label>
        <select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()" style="width:120px;">
            @foreach($exercices as $ex)
                <option value="{{ $ex->id }}" @selected($exercice->id === $ex->id)>{{ $ex->annee }}</option>
            @endforeach
        </select>
    </form>
    @endif
</div>

@if($exercice && $data)
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm border-start border-3 border-primary"><div class="card-body text-center">
        <div class="text-muted small">Score global S&E</div>
        <div class="fs-3 fw-bold text-primary">{{ $s['score_global'] ?? 0 }}%</div>
        <span class="badge bg-{{ $badgeNiveau }}">{{ \App\Models\EvaluationPeriodique::NIVEAUX[$niveau] ?? $niveau }}</span>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Physique PAP</div><div class="fs-4 fw-bold">{{ $s['score_physique'] ?? 0 }}%</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Indicateurs IOV</div><div class="fs-4 fw-bold">{{ $s['score_iov'] ?? 0 }}%</div>
        <small class="text-muted">{{ ($data['feux']['vert'] ?? 0) }}V / {{ ($data['feux']['orange'] ?? 0) }}O / {{ ($data['feux']['rouge'] ?? 0) }}R</small>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Conformité suivi</div><div class="fs-4 fw-bold">{{ $s['score_conformite'] ?? 0 }}%</div>
        <small class="text-muted">{{ $data['audit']['anomalies_total'] ?? 0 }} anomalie(s)</small>
    </div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Recommandations</div>
        <div class="fs-5 fw-bold">{{ $data['recommandations']['realisees'] ?? 0 }} / {{ $data['recommandations']['total'] ?? 0 }} réalisées</div>
        <div class="small">Score : {{ $s['score_recommandations'] ?? 0 }}% · exécution moy. {{ $data['recommandations']['taux_execution_moyen'] ?? 0 }}%</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Formations / ateliers</div>
        <div class="fs-5 fw-bold">{{ $data['formations']['realisees'] ?? 0 }} / {{ $data['formations']['total'] ?? 0 }} réalisées</div>
        <div class="small">Score : {{ $s['score_formations'] ?? 0 }}%</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Missions S&E</div>
        <div class="fs-5 fw-bold">{{ $data['missions']['en_cours'] ?? 0 }} en cours</div>
        <div class="small">{{ $data['missions']['total'] ?? 0 }} mission(s) · {{ $data['missions']['cloturees'] ?? 0 }} clôturée(s)</div>
    </div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Plans d'amélioration</div>
        <div class="fs-5 fw-bold">{{ $data['plans']['en_cours'] ?? 0 }} en cours</div>
        <div class="small">Taux moyen : {{ $data['plans']['taux_moyen'] ?? 0 }}% · {{ $data['plans']['total'] ?? 0 }} plan(s)</div>
    </div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Couverture PAP</div>
        <div class="fs-5 fw-bold">{{ $data['audit']['synthese']['taux_couverture'] ?? 0 }}%</div>
        <div class="small">{{ $data['audit']['synthese']['collectes_validees'] ?? 0 }} collectes validées</div>
    </div></div></div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    @can('suivi.voir')
    <a href="{{ route('suivi.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar-check me-1"></i>Campagnes</a>
    @endcan
    @can('recommandation.voir')
    <a href="{{ route('recommandations.index', ['exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-square me-1"></i>Recommandations</a>
    @endcan
    @canany(['suivi_evaluation.create', 'suivi.creer'])
    <a href="{{ route('suivi-evaluation.missions.index', ['exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-binoculars me-1"></i>Missions</a>
    <a href="{{ route('suivi-evaluation.plans.index', ['exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-list-check me-1"></i>Plans d'amélioration</a>
    @endcanany
    @can('indicateur.voir')
    <a href="{{ route('rbm.suivi-indicateurs.hub', ['exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-info"><i class="bi bi-speedometer2 me-1"></i>Indicateurs IOV</a>
    @endcan
    @canany(['suivi_evaluation.export', 'rapport.voir'])
    <a href="{{ route('reporting.show', ['type' => 'suivi_evaluation', 'exercice' => $exercice->id]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-bar-graph me-1"></i>Rapport S&E</a>
    @endcanany
    @canany(['suivi_evaluation.validate', 'suivi.valider'])
    <form method="POST" action="{{ route('suivi-evaluation.generer') }}" class="d-inline">
        @csrf
        <input type="hidden" name="exercice" value="{{ $exercice->id }}">
        <button class="btn btn-sm btn-success"><i class="bi bi-camera me-1"></i>Générer évaluation annuelle</button>
    </form>
    <form method="POST" action="{{ route('suivi-evaluation.generer-impact') }}" class="d-inline">
        @csrf
        <input type="hidden" name="exercice" value="{{ $exercice->id }}">
        <button class="btn btn-sm btn-primary" title="Évaluation d’impact de fin de cycle"><i class="bi bi-graph-up-arrow me-1"></i>Générer évaluation d’impact</button>
    </form>
    @endcanany
</div>

@if(($data['evaluations'] ?? collect())->isNotEmpty())
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-bold">Évaluations périodiques enregistrées</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Période</th><th>Type</th><th>Score</th><th>Niveau</th><th>Généré le</th><th></th></tr></thead>
            <tbody>
            @foreach($data['evaluations'] as $eval)
                <tr>
                    <td>{{ $eval->periode }}</td>
                    <td><span class="badge bg-{{ $eval->type === 'impact' ? 'primary' : 'secondary' }}-subtle text-{{ $eval->type === 'impact' ? 'primary' : 'secondary' }}">{{ \App\Models\EvaluationPeriodique::TYPES[$eval->type] ?? $eval->type }}</span></td>
                    <td><strong>{{ $eval->score_global }}%</strong></td>
                    <td><span class="badge bg-{{ match($eval->niveau) { 'satisfaisant' => 'success', 'partiel' => 'warning', default => 'danger' } }}">{{ \App\Models\EvaluationPeriodique::NIVEAUX[$eval->niveau] ?? $eval->niveau }}</span></td>
                    <td>{{ $eval->genere_le?->format('d/m/Y H:i') }}</td>
                    <td><a href="{{ route('suivi-evaluation.evaluations.show', $eval) }}" class="btn btn-sm btn-link">Détail</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@else
<div class="alert alert-info">Sélectionnez un exercice pour afficher la synthèse S&E.</div>
@endif
@endsection
