@extends('layouts.app')
@section('titre', 'Piste d\'audit')

@section('contenu')
@php
    $libellesPerimetre = ['etendu' => 'Périmètre étendu', 'unite' => 'Votre unité et vos propres actions', 'propre' => 'Vos propres actions'];
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-shield-lock me-2"></i>Journal des opérations</h4>
        <span class="text-muted small">{{ $libellesPerimetre[$perimetre] }} · {{ $journaux->total() }} événement(s) · heures affichées à {{ \App\Support\Horodatage::fuseau() }}, enregistrées en UTC</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('audit.tableau-de-bord') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-speedometer2 me-1"></i>Tableau de bord</a>
        @can('audit.exports.download')
            <a href="{{ route('audit.export', ['format' => 'csv'] + $filtres) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
            <a href="{{ route('audit.export', ['format' => 'json'] + $filtres) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-filetype-json me-1"></i>JSON</a>
        @endcan
    </div>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3"><div class="card-body py-2"><div class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Du</label><input type="date" name="du" value="{{ $filtres['du'] ?? '' }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Au</label><input type="date" name="au" value="{{ $filtres['au'] ?? '' }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Catégorie</label>
        <select name="categorie" class="form-select form-select-sm"><option value="">Toutes</option>@foreach ($options['categories'] as $c => $l)<option value="{{ $c }}" @selected(($filtres['categorie'] ?? '') === $c)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Module</label>
        <select name="module" class="form-select form-select-sm"><option value="">Tous</option>@foreach ($options['modules'] as $m)<option value="{{ $m }}" @selected(($filtres['module'] ?? '') === $m)>{{ $m }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Action</label>
        <select name="action" class="form-select form-select-sm"><option value="">Toutes</option>@foreach ($options['actions'] as $a)<option value="{{ $a }}" @selected(($filtres['action'] ?? '') === $a)>{{ $a }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Résultat</label>
        <select name="resultat" class="form-select form-select-sm"><option value="">Tous</option>@foreach ($options['resultats'] as $c => $l)<option value="{{ $c }}" @selected(($filtres['resultat'] ?? '') === $c)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Gravité</label>
        <select name="gravite" class="form-select form-select-sm"><option value="">Toutes</option>@foreach ($options['gravites'] as $c => $l)<option value="{{ $c }}" @selected(($filtres['gravite'] ?? '') === $c)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Acteur</label><input type="text" name="acteur" value="{{ $filtres['acteur'] ?? '' }}" class="form-control form-control-sm" placeholder="Nom"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Référence dossier</label><input type="text" name="reference" value="{{ $filtres['reference'] ?? '' }}" class="form-control form-control-sm" placeholder="EB-2026-…"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Corrélation</label><input type="text" name="correlation_id" value="{{ $filtres['correlation_id'] ?? '' }}" class="form-control form-control-sm" placeholder="EB-12"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Délégation</label>
        <select name="delegation" class="form-select form-select-sm"><option value="">Toutes</option><option value="1" @selected(($filtres['delegation'] ?? '') === '1')>Pour le compte d'un titulaire</option></select></div>
    <div class="col-12 col-md-2 d-flex gap-1">
        <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrer</button>
        <a href="{{ route('audit.index') }}" class="btn btn-sm btn-outline-secondary" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>
    </div>
</div></div></form>

@include('audit._table')
@endsection
