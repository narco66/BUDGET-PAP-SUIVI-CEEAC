@extends('layouts.app')
@section('titre', 'Référentiel RBM/GAR')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">Module 2 — Référentiel institutionnel RBM/GAR</h1>
        <p class="text-muted small mb-0">Pilier → Axe → Produit → Sous-produit → Activité → Tâche → Indicateurs</p>
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
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Chaîne hiérarchique</h2>
                <p class="small text-muted">Vue arborescente complète de la chaîne RBM/GAR.</p>
                <a href="{{ route('rbm.arbre', $exercice) }}" class="btn btn-sm btn-primary">Ouvrir l'arbre</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-grid-3x3-gap text-success me-2"></i>Matrice RBM/GAR</h2>
                <p class="small text-muted">Tableau aplatit Pilier → Activité avec montants et indicateurs.</p>
                <a href="{{ route('rbm.matrice', $exercice) }}" class="btn btn-sm btn-outline-success">Voir la matrice</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-signpost-split text-warning me-2"></i>Cadre logique</h2>
                <p class="small text-muted">Objectifs stratégiques et indicateurs par pilier/axe.</p>
                <a href="{{ route('rbm.cadre-logique', $exercice) }}" class="btn btn-sm btn-outline-warning">Cadre logique</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-arrow-down-right-square text-info me-2"></i>Chaîne de résultats</h2>
                <p class="small text-muted">Cartographie impact → activités opérationnelles.</p>
                <a href="{{ route('rbm.chaine-resultats', $exercice) }}" class="btn btn-sm btn-outline-info">Chaîne de résultats</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-tags text-secondary me-2"></i>Catalogues référentiels</h2>
                <p class="small text-muted">Types d'indicateurs, unités de mesure, sources de vérification.</p>
                <div class="d-flex flex-wrap gap-1">
                    <a href="{{ route('rbm.catalogues.index', 'types-indicateurs') }}" class="btn btn-sm btn-outline-secondary">Types</a>
                    <a href="{{ route('rbm.catalogues.index', 'unites-mesure') }}" class="btn btn-sm btn-outline-secondary">Unités</a>
                    <a href="{{ route('rbm.catalogues.index', 'sources-verification') }}" class="btn btn-sm btn-outline-secondary">Sources</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-speedometer2 text-danger me-2"></i>Suivi des indicateurs</h2>
                <p class="small text-muted">Pilotage IOV, feux, tendances et alertes performance.</p>
                <a href="{{ route('rbm.suivi-indicateurs.hub', $exercice) }}" class="btn btn-sm btn-outline-danger">Hub indicateurs</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>Restitution</h2>
                <p class="small text-muted">Exports Excel et synthèse PDF institutionnelle.</p>
                <div class="d-flex flex-wrap gap-1">
                    @can('rbm.importer')
                    <a href="{{ route('rbm.import.form') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i> Import Excel</a>
                    @endcan
                    @can('rbm.exporter')
                    <a href="{{ route('rbm.export.matrice', $exercice) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Matrice</a>
                    <a href="{{ route('rbm.export.cadre-logique', $exercice) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Cadre logique</a>
                    @endcan
                    @canany(['rbm.exporter','indicateur.exporter'])
                    <a href="{{ route('rbm.export.indicateurs', $exercice) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Indicateurs</a>
                    @endcanany
                    @can('rbm.generer_pdf')
                    <a href="{{ route('rbm.synthese.pdf', $exercice) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> Synthèse</a>
                    <a href="{{ route('rbm.pdf.matrice', $exercice) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> Matrice PDF</a>
                    <a href="{{ route('rbm.pdf.cadre-logique', $exercice) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> Cadre logique PDF</a>
                    <a href="{{ route('rbm.pdf.indicateurs', $exercice) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf"></i> Indicateurs PDF</a>
                    @endcan
                    @can('rbm.exporter')
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Archiver une version officielle traçable (versioning, hash, QR, GED)">
                            <i class="bi bi-shield-check"></i> Archiver (officiel)
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Excel</h6></li>
                            @foreach (['matrice' => 'Matrice', 'cadre-logique' => 'Cadre logique', 'indicateurs' => 'Indicateurs'] as $cle => $libelle)
                                <li><a class="dropdown-item" href="{{ route('rbm.export.archiver', [$exercice, $cle]) }}"><i class="bi bi-file-earmark-excel me-2"></i>{{ $libelle }}</a></li>
                            @endforeach
                            @can('rbm.generer_pdf')
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">PDF</h6></li>
                            @foreach (['matrice' => 'Matrice', 'cadre-logique' => 'Cadre logique', 'indicateurs' => 'Indicateurs'] as $cle => $libelle)
                                <li><a class="dropdown-item" href="{{ route('rbm.pdf.archiver', [$exercice, $cle]) }}"><i class="bi bi-file-pdf me-2"></i>{{ $libelle }}</a></li>
                            @endforeach
                            @endcan
                        </ul>
                    </div>
                    @endcan
                </div>
            </div>
        </div>
    </div>
    @canany(['rbm.consulter_journal','audit.voir'])
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-clock-history text-info me-2"></i>Traçabilité</h2>
                <p class="small text-muted">Historique des créations, modifications, archivages et suppressions.</p>
                <a href="{{ route('rbm.historique.index', $exercice) }}" class="btn btn-sm btn-outline-info">Journal des modifications</a>
            </div>
        </div>
    </div>
    @endcanany
    @can('ged.voir')
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-folder2-open text-primary me-2"></i>Documents GED</h2>
                <p class="small text-muted">Pièces et exports RBM indexés dans la gestion documentaire.</p>
                <a href="{{ route('ged.recherche', ['module_source' => 'RBM']) }}" class="btn btn-sm btn-outline-primary">Rechercher docs RBM</a>
                <a href="{{ route('ged.documents.create', ['module_source' => 'RBM']) }}" class="btn btn-sm btn-primary ms-1">Déposer</a>
            </div>
        </div>
    </div>
    @endcan
    @canany(['rbm.admin', 'rbm.modifier'])
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-copy text-secondary me-2"></i>Report inter-exercices</h2>
                <p class="small text-muted">Dupliquer la chaîne RBM vers un exercice vide (sans mesures ni budget).</p>
                <form method="POST" action="{{ route('rbm.reporter-exercice') }}" class="small">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label mb-0">Source</label>
                        <select name="exercice_source" class="form-select form-select-sm" required>
                            @foreach($exercices as $ex)
                                <option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>{{ $ex->annee }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-0">Cible</label>
                        <select name="exercice_cible" class="form-select form-select-sm" required>
                            @foreach($exercices as $ex)
                                <option value="{{ $ex->id }}">{{ $ex->annee }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Reporter le référentiel</button>
                </form>
            </div>
        </div>
    </div>
    @endcanany
</div>
@else
<div class="alert alert-warning">Aucun exercice budgétaire disponible. Créez un exercice pour alimenter le référentiel RBM.</div>
@endif
@endsection
