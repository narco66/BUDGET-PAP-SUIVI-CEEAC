@extends('layouts.app')
@section('titre', 'Référentiel financier')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1">Référentiel financier CEEAC</h1>
        <p class="text-muted small mb-0">Exercices · Sources · PTF · Nomenclature · Budget · Contrôles d'intégrité</p>
    </div>
    @if($exercice)
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="small text-muted mb-0">Exercice</label>
        <select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()" style="width:120px;">
            @foreach($exercices as $ex)
                <option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>{{ $ex->annee }}</option>
            @endforeach
        </select>
    </form>
    @endif
</div>

@if($exercice)
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="fs-4 fw-bold text-primary">{{ $stats['comptes']['exercices'] }}</div>
        <div class="small text-muted">Exercices</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="fs-4 fw-bold text-success">{{ $stats['comptes']['sources_actives'] }}</div>
        <div class="small text-muted">Sources actives</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="fs-4 fw-bold text-info">{{ $stats['comptes']['partenaires_actifs'] }}</div>
        <div class="small text-muted">Partenaires PTF</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="fs-4 fw-bold text-warning">{{ $stats['comptes']['lignes_budget'] }}</div>
        <div class="small text-muted">Lignes budget {{ $exercice->annee }}</div>
    </div></div></div>
</div>
@endif

<div class="row g-3 mb-4">
    @can('exercice.voir')
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-calendar-range text-primary me-2"></i>Exercices budgétaires</h2>
                <p class="small text-muted">Ouverture, clôture, exercice actif et verrouillage transversal.</p>
                <a href="{{ route('exercices.index') }}" class="btn btn-sm btn-primary">Gérer les exercices</a>
            </div>
        </div>
    </div>
    @endcan
    @can('source_financement.voir')
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-piggy-bank text-success me-2"></i>Sources de financement</h2>
                <p class="small text-muted">Contributions CEEAC, subventions, coopérations, fonds spéciaux.</p>
                <a href="{{ route('sources.index') }}" class="btn btn-sm btn-outline-success">Sources</a>
            </div>
        </div>
    </div>
    @endcan
    @can('partenaire_financier.voir')
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-bank text-info me-2"></i>Partenaires (PTF)</h2>
                <p class="small text-muted">Bailleurs, conventions et accords de financement.</p>
                <a href="{{ route('partenaires.index') }}" class="btn btn-sm btn-outline-info">Partenaires</a>
            </div>
        </div>
    </div>
    @endcan
    @can('nomenclature.voir')
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-list-nested text-secondary me-2"></i>Nomenclature budgétaire</h2>
                <p class="small text-muted">Titre → Article → Paragraphe → Rubrique ({{ $stats['comptes']['rubriques'] }} rubriques).</p>
                <a href="{{ route('nomenclature.index') }}" class="btn btn-sm btn-outline-secondary">Nomenclature</a>
            </div>
        </div>
    </div>
    @endcan
    @can('ligne_budgetaire.voir')
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-cash-stack text-warning me-2"></i>Budget & crédits</h2>
                <p class="small text-muted">Lignes budgétaires, ventilation multi-sources, imports Excel.</p>
                <a href="{{ route('budget.index', ['exercice' => $exercice?->id]) }}" class="btn btn-sm btn-outline-warning">Budget</a>
                @can('ligne_budgetaire.importer')
                <a href="{{ route('budget.import.form') }}" class="btn btn-sm btn-outline-primary ms-1">Import</a>
                @endcan
            </div>
        </div>
    </div>
    @endcan
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-shield-check text-danger me-2"></i>Contrôles d'intégrité</h2>
                <p class="small text-muted">
                    Audit : <strong>{{ $stats['audit']['anomalies_total'] ?? 0 }}</strong> anomalie(s) détectée(s).
                    Multi-sources : {{ $stats['financement']['multi_sources'] ?? 0 }} ligne(s).
                </p>
                <code class="small d-block mb-2">php artisan budget:audit @if($exercice) --exercice={{ $exercice->annee }} @endif</code>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-download me-2"></i>Exports référentiel</div>
    <div class="card-body d-flex flex-wrap gap-2">
        @can('source_financement.exporter')
        <a href="{{ route('referentiel.export.sources') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Sources Excel</a>
        @endcan
        @can('partenaire_financier.exporter')
        <a href="{{ route('referentiel.export.partenaires') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>PTF Excel</a>
        @endcan
        @can('convention_financement.exporter')
        <a href="{{ route('referentiel.export.conventions', ['exercice' => $exercice?->id]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Conventions Excel</a>
        @endcan
        @if(auth()->user()->can('nomenclature.generer_pdf') || auth()->user()->can('source_financement.exporter'))
        <a href="{{ route('referentiel.pdf.synthese', ['exercice' => $exercice?->id]) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>Synthèse PDF</a>
        <a href="{{ route('referentiel.pdf.sources') }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>Sources PDF</a>
        <a href="{{ route('referentiel.pdf.ptf', ['exercice' => $exercice?->id]) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>PTF PDF</a>
        @endif
        @canany(['source_financement.exporter', 'partenaire_financier.exporter', 'convention_financement.exporter'])
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Archiver une version officielle traçable (versioning, hash, QR, GED)">
                <i class="bi bi-shield-check me-1"></i>Archiver (officiel)
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Excel</h6></li>
                @can('source_financement.exporter')<li><a class="dropdown-item" href="{{ route('referentiel.export.archiver', 'sources') }}"><i class="bi bi-file-earmark-excel me-2"></i>Sources</a></li>@endcan
                @can('partenaire_financier.exporter')<li><a class="dropdown-item" href="{{ route('referentiel.export.archiver', 'partenaires') }}"><i class="bi bi-file-earmark-excel me-2"></i>Partenaires (PTF)</a></li>@endcan
                @can('convention_financement.exporter')<li><a class="dropdown-item" href="{{ route('referentiel.export.archiver', ['cle' => 'conventions', 'exercice' => $exercice?->id]) }}"><i class="bi bi-file-earmark-excel me-2"></i>Conventions</a></li>@endcan
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">PDF</h6></li>
                <li><a class="dropdown-item" href="{{ route('referentiel.pdf.archiver', ['cle' => 'synthese', 'exercice' => $exercice?->id]) }}"><i class="bi bi-file-pdf me-2"></i>Synthèse</a></li>
                <li><a class="dropdown-item" href="{{ route('referentiel.pdf.archiver', 'sources') }}"><i class="bi bi-file-pdf me-2"></i>Sources</a></li>
                <li><a class="dropdown-item" href="{{ route('referentiel.pdf.archiver', ['cle' => 'ptf', 'exercice' => $exercice?->id]) }}"><i class="bi bi-file-pdf me-2"></i>Partenaires (PTF)</a></li>
            </ul>
        </div>
        @endcanany
    </div>
</div>

@if(($stats['audit']['anomalies_total'] ?? 0) > 0)
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-1"></i>
    {{ $stats['audit']['anomalies_total'] }} anomalie(s) d'intégrité — consultez <code>budget:audit --json</code> pour le détail.
</div>
@else
<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i>Référentiel financier : aucune anomalie critique détectée sur l'exercice sélectionné.</div>
@endif
@endsection
