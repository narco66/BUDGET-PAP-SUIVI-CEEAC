@extends('layouts.app')
@section('titre', 'États & rapports des recettes')

@php
    $q = $exercice ? '?exercice='.$exercice->id : '';
@endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route('recettes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Recettes</a>
    <form method="GET">
        <select name="exercice" class="form-select form-select-sm" style="width:170px;" onchange="this.form.submit()">
            @foreach ($exercices as $ex)
                <option value="{{ $ex->id }}" @selected($exercice && $exercice->id === $ex->id)>Exercice {{ $ex->annee }}</option>
            @endforeach
        </select>
    </form>
</div>

@cannot('recette.exporter')
    <div class="alert alert-warning">Vous n'avez pas la permission d'exporter les états des recettes.</div>
@endcannot

<div class="row g-3">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm h-100 border-start border-3 border-primary"><div class="card-body">
            <h6><i class="bi bi-file-earmark-richtext me-1 text-primary"></i>Rapport financier consolidé</h6>
            <p class="small text-muted">Synthèse + mobilisation par État + arriérés, en un document institutionnel.</p>
            @can('recette.exporter')
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('recettes.rapports.financier.pdf') }}{{ $q }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>PDF</a>
                <a href="{{ route('recettes.rapports.financier.word') }}{{ $q }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-word me-1"></i>Word</a>
                <a href="{{ route('recettes.rapports.archiver', ['cle' => 'rapport-financier', 'format' => 'pdf']) }}{{ $q }}" class="btn btn-sm btn-outline-dark" title="Archiver une version officielle traçable (versioning, hash, QR, GED)"><i class="bi bi-shield-check me-1"></i>Archiver</a>
            </div>
            @endcan
        </div></div>
    </div>

    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6><i class="bi bi-globe2 me-1 text-primary"></i>Tableau de mobilisation</h6>
            <p class="small text-muted">Contributions par État membre : appelé, recouvré, arriéré, taux.</p>
            @can('recette.exporter')
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('recettes.rapports.mobilisation.pdf') }}{{ $q }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>PDF</a>
                <a href="{{ route('recettes.rapports.mobilisation.excel') }}{{ $q }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Excel</a>
                <a href="{{ route('recettes.rapports.mobilisation.csv') }}{{ $q }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                <a href="{{ route('recettes.rapports.archiver', ['cle' => 'mobilisation', 'format' => 'pdf']) }}{{ $q }}" class="btn btn-sm btn-outline-dark" title="Archiver une version officielle traçable"><i class="bi bi-shield-check me-1"></i>Archiver</a>
            </div>
            @endcan
        </div></div>
    </div>

    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6><i class="bi bi-exclamation-octagon me-1 text-danger"></i>État des arriérés</h6>
            <p class="small text-muted">Appels de fonds non soldés, avec pénalités.</p>
            @can('recette.exporter')
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('recettes.rapports.arrieres.pdf') }}{{ $q }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>PDF</a>
                <a href="{{ route('recettes.rapports.arrieres.excel') }}{{ $q }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Excel</a>
                <a href="{{ route('recettes.rapports.archiver', ['cle' => 'arrieres', 'format' => 'pdf']) }}{{ $q }}" class="btn btn-sm btn-outline-dark" title="Archiver une version officielle traçable"><i class="bi bi-shield-check me-1"></i>Archiver</a>
            </div>
            @endcan
        </div></div>
    </div>

    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6><i class="bi bi-bank me-1 text-success"></i>État des encaissements</h6>
            <p class="small text-muted">Encaissements comptabilisés (validés / rapprochés).</p>
            @can('recette.exporter')
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('recettes.rapports.encaissements.excel') }}{{ $q }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Excel</a>
                <a href="{{ route('recettes.rapports.encaissements.csv') }}{{ $q }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                <a href="{{ route('recettes.rapports.archiver', ['cle' => 'encaissements', 'format' => 'excel']) }}{{ $q }}" class="btn btn-sm btn-outline-dark" title="Archiver une version officielle traçable"><i class="bi bi-shield-check me-1"></i>Archiver</a>
            </div>
            @endcan
        </div></div>
    </div>

    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6><i class="bi bi-graph-up-arrow me-1 text-primary"></i>État des prévisions</h6>
            <p class="small text-muted">Prévisions courantes : prévu, révisé, retenu.</p>
            @can('recette.exporter')
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('recettes.rapports.previsions.excel') }}{{ $q }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Excel</a>
                <a href="{{ route('recettes.rapports.previsions.csv') }}{{ $q }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                <a href="{{ route('recettes.rapports.archiver', ['cle' => 'previsions', 'format' => 'excel']) }}{{ $q }}" class="btn btn-sm btn-outline-dark" title="Archiver une version officielle traçable"><i class="bi bi-shield-check me-1"></i>Archiver</a>
            </div>
            @endcan
        </div></div>
    </div>
</div>
@endsection
