@extends('layouts.app')
@section('titre', 'Documents GED')

@section('contenu')
@include('ged._nav-module', ['page' => 'documents'])

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Registre documentaire</h4>
    <div class="d-flex gap-2">
        @can('ged.exporter')
        <a href="{{ route('ged.exports.documents.excel') }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Archiver un registre officiel traçable (versioning, hash, QR)">
                <i class="bi bi-shield-check me-1"></i>Archiver (officiel)
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach (['documents' => 'Registre des documents', 'archives' => 'Registre des archives', 'signatures' => 'Registre des signatures'] as $cle => $libelle)
                    <li><a class="dropdown-item" href="{{ route('ged.exports.registres.archiver', $cle) }}"><i class="bi bi-file-earmark-check me-2"></i>{{ $libelle }}</a></li>
                @endforeach
                @can('ged.consulter_journal')
                <li><a class="dropdown-item" href="{{ route('ged.exports.registres.archiver', 'journal') }}"><i class="bi bi-file-earmark-check me-2"></i>Journal documentaire</a></li>
                @endcan
            </ul>
        </div>
        @endcan
        @can('create', \App\Models\Ged\GedDocument::class)
        <a href="{{ route('ged.documents.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau</a>
        @endcan
    </div>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Recherche</label>
            <input type="text" name="q" class="form-control form-control-sm" value="{{ $filtres['q'] ?? '' }}" placeholder="Titre, référence…">
        </div>
        <div class="col-md-2">
            <label class="form-label small">Statut</label>
            <select name="statut" class="form-select form-select-sm">
                <option value="">Tous</option>
                @foreach ($statuts as $val => $lib)
                    <option value="{{ $val }}" @selected(($filtres['statut'] ?? '') === $val)>{{ $lib }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Confidentialité</label>
            <select name="niveau_confidentialite" class="form-select form-select-sm">
                <option value="">Tous</option>
                @foreach ($niveaux as $val => $lib)
                    <option value="{{ $val }}" @selected(($filtres['niveau_confidentialite'] ?? '') === $val)>{{ $lib }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Filtrer</button>
        </div>
    </div>
</form>

@include('ged.partials._liste-documents', ['documents' => $documents])
@endsection
