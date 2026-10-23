@extends('layouts.app')
@section('titre', 'Structure du budget')

@push('styles')<style>
    .sb-tree details { margin:.1rem 0; }
    .sb-tree summary { cursor:pointer; padding:.3rem .5rem; border-radius:.35rem; list-style:none; }
    .sb-tree summary:hover { background:#eef3f9; }
    .sb-tree summary::-webkit-details-marker { display:none; }
    .sb-node { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
    .sb-mt { font-variant-numeric:tabular-nums; }
    .sb-code { font-family:monospace; color:#0a4a8f; }
    .sb-child { margin-left:1.4rem; border-left:1px dashed #cdd7e3; padding-left:.4rem; }
</style>@endpush

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Structure du budget</h4>
        <span class="text-muted small">Exercice → Département → Direction → Service → … → Ligne budgétaire (montants consolidés)</span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('structure-budget.tableau', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-info btn-sm"><i class="bi bi-speedometer2 me-1"></i>Tableau de bord</a>
        <a href="{{ route('structure-budget.arbre-complet', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-diagram-3-fill me-1"></i>Arbre complet</a>
        <a href="{{ route('structure-budget.recherche', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search me-1"></i>Recherche</a>
        @can('structure_budget.consulter_journal')
            <a href="{{ route('structure-budget.journal', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-clock-history me-1"></i>Journal</a>
        @elsecan('audit.voir')
            <a href="{{ route('structure-budget.journal', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-dark btn-sm"><i class="bi bi-clock-history me-1"></i>Journal</a>
        @endcan
        @can('export', \App\Authorization\StructureBudget::class)
            <div class="btn-group">
                <a href="{{ route('structure-budget.export', ['exercice' => $exercice?->id, 'format' => 'xlsx']) }}" class="btn btn-outline-success btn-sm" title="Export Excel"><i class="bi bi-file-earmark-excel"></i></a>
                <a href="{{ route('structure-budget.export', ['exercice' => $exercice?->id, 'format' => 'csv']) }}" class="btn btn-outline-success btn-sm" title="Export CSV"><i class="bi bi-filetype-csv"></i></a>
                <a href="{{ route('structure-budget.export', ['exercice' => $exercice?->id, 'format' => 'pdf']) }}" class="btn btn-outline-success btn-sm" title="Export PDF"><i class="bi bi-file-earmark-pdf"></i></a>
            </div>
            <a href="{{ route('structure-budget.import-export', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Import/Export</a>
        @endcan
        @can('create', \App\Authorization\StructureBudget::class)
            <a href="{{ route('structure-budget.wizard', ['exercice' => $exercice?->id]) }}" class="btn btn-success btn-sm"><i class="bi bi-lightning-charge me-1"></i>Assistant de saisie</a>
        @endcan
        <form method="GET"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
        </select></form>
    </div>
</div>

<div class="row g-3 mb-3">
    @php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold">{{ $synthese['nb_structures'] }}</div><div class="text-muted small">Structures budgétées</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold">{{ $synthese['nb_lignes'] }}</div><div class="text-muted small">Lignes budgétaires</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold sb-mt">{{ $fmt($synthese['ouvert']) }}</div><div class="text-muted small">Crédit ouvert (XAF)</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold text-warning">{{ $synthese['non_rattachees'] }}</div><div class="text-muted small">Lignes non rattachées</div></div></div></div>
</div>

<div class="card border-0 shadow-sm"><div class="card-body sb-tree">
    @forelse ($arbre as $noeud)
        @include('structure-budget._noeud', ['noeud' => $noeud, 'exercice' => $exercice])
    @empty
        <p class="text-muted text-center py-4 mb-0">Aucune structure. Créez l'organigramme puis rattachez des lignes budgétaires.</p>
    @endforelse
</div></div>
@endsection
