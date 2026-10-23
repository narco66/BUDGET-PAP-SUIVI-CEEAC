@extends('layouts.app')
@section('titre', 'Arbre complet — Structure du budget')

@push('styles')<style>
    .sb-tree details { margin:.1rem 0; }
    .sb-tree summary { cursor:pointer; padding:.3rem .5rem; border-radius:.35rem; list-style:none; }
    .sb-tree summary:hover { background:#eef3f9; }
    .sb-tree summary::-webkit-details-marker { display:none; }
    .sb-node { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
    .sb-mt { font-variant-numeric:tabular-nums; }
    .sb-code { font-family:monospace; color:#0a4a8f; }
    .sb-child { margin-left:1.4rem; border-left:1px dashed #cdd7e3; padding-left:.4rem; }
    .sb-type-pilier { color:#6f42c1; } .sb-type-axe { color:#0d6efd; }
    .sb-type-produit { color:#198754; } .sb-type-activite { color:#fd7e14; }
</style>@endpush

@section('contenu')
@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $synthese = $donnees['synthese'];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Arbre complet du budget</h4>
        <span class="text-muted small">Organigramme institutionnel + chaîne programmatique PAP (Pilier → Activité → Ligne)</span>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <a href="{{ route('structure-budget.arbre', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-secondary btn-sm">Vue institutionnelle</a>
        <a href="{{ route('structure-budget.recherche', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-search me-1"></i>Recherche</a>
        @can('export', \App\Authorization\StructureBudget::class)
            <div class="btn-group btn-group-sm">
                <a href="{{ route('structure-budget.export', ['exercice' => $exercice?->id, 'format' => 'xlsx']) }}" class="btn btn-outline-success" title="Excel"><i class="bi bi-file-earmark-excel"></i></a>
                <a href="{{ route('structure-budget.export', ['exercice' => $exercice?->id, 'format' => 'pdf']) }}" class="btn btn-outline-success" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
            </div>
            <a href="{{ route('structure-budget.import-export', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-secondary btn-sm">Import/Export</a>
        @endcan
        @can('create', \App\Authorization\StructureBudget::class)
            <a href="{{ route('structure-budget.wizard', ['exercice' => $exercice?->id]) }}" class="btn btn-success btn-sm"><i class="bi bi-plus-lg me-1"></i>Assistant</a>
        @endcan
        <form method="GET"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
        </select></form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold">{{ $synthese['nb_lignes'] }}</div><div class="text-muted small">Lignes budgétaires</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold sb-mt">{{ $fmt($synthese['ouvert']) }}</div><div class="text-muted small">Crédit ouvert (XAF)</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold text-primary sb-mt">{{ $fmt($synthese['engage']) }}</div><div class="text-muted small">Engagé</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold sb-mt">{{ $fmt($synthese['paye']) }}</div><div class="text-muted small">Payé</div></div></div></div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-inst" type="button">Organigramme</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pap" type="button">Chaîne PAP / RBM</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-inst">
        <div class="card border-0 shadow-sm"><div class="card-body sb-tree">
            @forelse ($donnees['institutionnel'] as $noeud)
                @include('structure-budget._noeud', ['noeud' => $noeud, 'exercice' => $exercice])
            @empty
                <p class="text-muted text-center py-4 mb-0">Aucune structure budgétée.</p>
            @endforelse
        </div></div>
    </div>
    <div class="tab-pane fade" id="tab-pap">
        <div class="card border-0 shadow-sm"><div class="card-body sb-tree">
            @forelse ($donnees['programmatique'] as $noeud)
                @include('structure-budget._noeud-pap', ['noeud' => $noeud, 'exercice' => $exercice])
            @empty
                <p class="text-muted text-center py-4 mb-0">Aucun pilier PAP pour cet exercice.</p>
            @endforelse
        </div></div>
    </div>
</div>
@endsection
