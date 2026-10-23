@extends('layouts.app')
@section('titre', 'Import / Export — Structure du budget')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp

@section('contenu')
<a href="{{ route('structure-budget.arbre', ['exercice' => $exercice?->id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Structure du budget</a>
<h4 class="fw-bold mb-1">Import / Export consolidés</h4>
<p class="text-muted small">Exercice {{ $exercice?->annee ?? '—' }} · {{ $synthese['nb_lignes'] ?? 0 }} ligne(s) · {{ $fmt($synthese['ouvert'] ?? 0) }} XAF ouverts</p>

<div class="row g-3">
    @if($peutExporter)
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-download me-1"></i>Export</div>
            <div class="card-body">
                <p class="small text-muted">Export consolidé avec structure, PAP, nomenclature et montants d'exécution.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('structure-budget.export', ['exercice' => $exercice?->id, 'format' => 'xlsx']) }}" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-file-earmark-excel me-1"></i>Excel (.xlsx)
                    </a>
                    <a href="{{ route('structure-budget.export', ['exercice' => $exercice?->id, 'format' => 'csv']) }}" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-filetype-csv me-1"></i>CSV
                    </a>
                    <a href="{{ route('structure-budget.export', ['exercice' => $exercice?->id, 'format' => 'pdf']) }}" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($peutImporter)
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-upload me-1"></i>Import</div>
            <div class="card-body">
                <p class="small text-muted">Prévisualisation, validation ligne à ligne, rapport d'erreurs. Réutilise la résolution nomenclature existante.</p>
                <a href="{{ route('structure-budget.import.modele') }}" class="btn btn-outline-secondary btn-sm mb-3">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Télécharger le modèle Excel
                </a>
                <form method="POST" action="{{ route('structure-budget.import.apercu') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="exercice_id" value="{{ $exercice?->id }}">
                    <div class="mb-2">
                        <input type="file" name="fichier" class="form-control form-control-sm" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-eye me-1"></i>Prévisualiser l'import</button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>

@if(session('rapport_import'))
    @php $r = session('rapport_import'); @endphp
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold">Rapport du dernier import</div>
        <div class="card-body small">
            <p class="mb-2"><strong>{{ $r['importees'] }}</strong> ligne(s) importée(s).
                @if($r['rejets_total'] > 0)<span class="text-danger">{{ $r['rejets_total'] }} rejet(s).</span>@endif
            </p>
            @if(!empty($r['rejets']))
                <ul class="mb-0">@foreach($r['rejets'] as $rej)<li>Ligne {{ $rej['ligne'] }} — {{ $rej['motif'] }}</li>@endforeach</ul>
            @endif
        </div>
    </div>
@endif

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold text-muted">Imports existants (modules dédiés)</div>
    <div class="card-body small">
        <p class="mb-2">Les imports historiques restent disponibles sans modification :</p>
        <a href="{{ route('budget.import.form', ['exercice' => $exercice?->id]) }}" class="btn btn-link btn-sm p-0">Budget & Crédits — nomenclature, lignes, PAP</a>
    </div>
</div>
@endsection
