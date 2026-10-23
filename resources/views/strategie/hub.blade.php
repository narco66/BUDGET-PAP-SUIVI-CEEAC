@extends('layouts.app')
@section('titre', 'Planification stratégique')

@section('contenu')
@include('strategie._nav', ['active' => 'hub'])

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">Planification stratégique institutionnelle</h1>
        <p class="text-muted small mb-0">Vision → Plan → Orientation → Programme → Projet → PAP / Budget</p>
    </div>
    <div class="d-flex flex-wrap gap-1">
        <a href="{{ route('strategie.aide') }}" class="btn btn-sm btn-outline-info"><i class="bi bi-book me-1"></i>Manuel</a>
    @can('strategie.export')
        <a href="{{ route('strategie.matrice') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-grid-3x3 me-1"></i>Matrice</a>
        <a href="{{ route('strategie.dashboard.decisionnel') }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-speedometer2 me-1"></i>Pilotage</a>
        <a href="{{ route('strategie.export.excel', 'matrice') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Matrice Excel</a>
        <a href="{{ route('strategie.export.pdf') }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>Synthèse PDF</a>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Archiver une version officielle traçable (versioning, hash, QR, GED)">
                <i class="bi bi-shield-check me-1"></i>Archiver (officiel)
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Rapports PDF</h6></li>
                @foreach (['synthese' => 'Synthèse stratégique', 'matrice' => "Matrice d'alignement", 'coherence' => 'Rapport de cohérence'] as $cle => $libelle)
                    <li><a class="dropdown-item" href="{{ route('strategie.export.archiver.pdf', $cle) }}"><i class="bi bi-file-pdf me-2"></i>{{ $libelle }}</a></li>
                @endforeach
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Catalogues Excel</h6></li>
                @foreach (['visions' => 'Visions', 'plans' => 'Plans', 'orientations' => 'Orientations', 'programmes' => 'Programmes', 'projets' => 'Projets', 'portefeuilles' => 'Portefeuilles', 'matrice' => 'Matrice', 'objectifs' => 'Objectifs', 'indicateurs' => 'Indicateurs', 'risques' => 'Risques'] as $cle => $libelle)
                    <li><a class="dropdown-item" href="{{ route('strategie.export.archiver.excel', $cle) }}"><i class="bi bi-file-excel me-2"></i>{{ $libelle }}</a></li>
                @endforeach
            </ul>
        </div>
    @endcan
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['Visions actives', $synthese['visions_actives'], 'primary'],
        ['Plans actifs', $synthese['plans_actifs'], 'success'],
        ['Programmes', $synthese['programmes'], 'info'],
        ['Projets', $synthese['projets'], 'warning'],
        ['Taux alignement', $synthese['taux_alignement'].'%', 'danger'],
        ['Budget programmé', number_format($synthese['budget_programme'], 0, ',', ' '), 'secondary'],
        ['Budget mobilisé', number_format($synthese['budget_mobilise'], 0, ',', ' '), 'dark'],
        ['Prog. pluri. actives', $synthese['programmations_pluri'], 'primary'],
        ['Objectifs actifs', $synthese['objectifs'], 'info'],
        ['Indicateurs actifs', $synthese['indicateurs'], 'success'],
        ['Risques ouverts', $synthese['risques'], 'danger'],
    ] as [$label, $val, $color])
    <div class="col-6 col-md-4 col-xl-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small">{{ $label }}</div>
            <div class="fs-6 fw-bold text-{{ $color }}">{{ $val }}@if(str_contains($label, 'Budget')) <span class="small fw-normal">XAF</span>@endif</div>
        </div></div>
    </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6">Cohérence stratégique</h2>
            <ul class="small mb-0">
                <li>Piliers RBM orientés : <strong>{{ $alignement['piliers_orientes_pct'] }}%</strong></li>
                <li>Activités rattachées à un projet : <strong>{{ $alignement['activites_projet_pct'] }}%</strong></li>
                <li>Alignements enregistrés : <strong>{{ $alignement['alignements_enregistres'] }}</strong></li>
            </ul>
            <a href="{{ route('strategie.rapport.coherence') }}" class="btn btn-sm btn-outline-primary mt-2">Rapport cohérence</a>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6">Programmation</h2>
            @if($programmation['projection'])
            <p class="small mb-1"><code>{{ $programmation['projection']->reference }}</code> · {{ $programmation['projection']->annee_debut }}-{{ $programmation['projection']->annee_fin }}</p>
            <p class="small text-muted mb-0">{{ $programmation['annuelles_actives'] }} déclinaison(s) annuelle(s) active(s)</p>
            @else
            <p class="small text-muted">Aucune programmation pluriannuelle active.</p>
            @endif
            <a href="{{ route('strategie.programmations-pluri.index') }}" class="btn btn-sm btn-outline-secondary mt-2 me-1">Programmations</a>
                <a href="{{ route('strategie.pont-pap') }}" class="btn btn-sm btn-outline-secondary mt-2">Pont PAP</a>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6">Actions rapides</h2>
            <div class="d-flex flex-wrap gap-1">
                @can('strategie.create')
                <a href="{{ route('strategie.objectifs.create') }}" class="btn btn-sm btn-outline-primary">+ Objectif</a>
                <a href="{{ route('strategie.alignements.create') }}" class="btn btn-sm btn-primary">+ Alignement</a>
                <a href="{{ route('strategie.programmations-pluri.create') }}" class="btn btn-sm btn-outline-primary">+ Prog. pluri.</a>
                @endcan
            </div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Visions récentes</div>
        <div class="list-group list-group-flush">@forelse($visions as $v)
            <a href="{{ route('strategie.visions.show', $v) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                <span><code>{{ $v->reference }}</code> — {{ Str::limit($v->intitule, 40) }}</span>@include('strategie._statut', ['statut' => $v->statut])
            </a>@empty<div class="list-group-item text-muted">Aucune vision.</div>@endforelse</div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Plans stratégiques</div>
        <div class="list-group list-group-flush">@forelse($plans as $p)
            <a href="{{ route('strategie.plans.show', $p) }}" class="list-group-item list-group-item-action">
                <div class="fw-semibold"><code>{{ $p->reference }}</code> — {{ Str::limit($p->intitule, 45) }}</div>
                <small class="text-muted">{{ $p->vision?->reference ?? '—' }}</small>
            </a>@empty<div class="list-group-item text-muted">Aucun plan.</div>@endforelse</div></div>
    </div>
</div>
@endsection
