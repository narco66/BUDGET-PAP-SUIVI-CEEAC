@extends('layouts.app')
@section('titre', 'Aperçu de l\'import nomenclature')

@section('contenu')
<a href="{{ route('budget.import.form') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Import budgétaire</a>
<h4 class="fw-bold mb-1">Prévisualisation de l'import</h4>
<p class="text-muted small">Fichier : <strong>{{ $fichier }}</strong> — vérifiez les données avant de confirmer. Rien n'est encore enregistré.</p>

{{-- Synthèse --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
        <div class="fs-4 fw-bold">{{ $total }}</div><div class="text-muted small">Lignes lues</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
        <div class="fs-4 fw-bold text-success">{{ $valides }}</div><div class="text-muted small">Valides</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
        <div class="fs-4 fw-bold text-danger">{{ $total - $valides }}</div><div class="text-muted small">À rejeter</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
        <div class="fs-4 fw-bold">{{ count($colonnesDetectees) }}</div><div class="text-muted small">Colonnes détectées</div></div></div></div>
</div>

@if($colonnesManquantes)
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Colonnes obligatoires manquantes :</strong> {{ implode(', ', $colonnesManquantes) }}.
    L'import échouera ou rejettera les lignes concernées. <a href="{{ route('budget.modele.nomenclature') }}">Télécharger le modèle</a>.
</div>
@endif

{{-- Aperçu des 10 premières lignes --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Aperçu (10 premières lignes)</div>
    <div class="table-responsive"><table class="table table-sm table-bordered mb-0 small">
        <thead class="table-light"><tr>@foreach($colonnesDetectees as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($apercu as $row)
            <tr>@foreach($colonnesDetectees as $col)<td>{{ \Illuminate\Support\Str::limit((string) (((array) $row)[$col] ?? ''), 30) }}</td>@endforeach</tr>
        @empty<tr><td colspan="{{ max(1, count($colonnesDetectees)) }}" class="text-center text-muted py-3">Fichier vide.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>

@if($rejets)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold text-danger">Lignes qui seront rejetées</div>
    <ul class="list-group list-group-flush small">
        @foreach($rejets as $r)<li class="list-group-item">Ligne {{ $r['ligne'] }} — {{ $r['motif'] }}</li>@endforeach
    </ul>
</div>
@endif

<div class="d-flex gap-2">
    @can('nomenclature.creer')
    <form action="{{ route('budget.import.nomenclature.confirmer') }}" method="POST">
        @csrf
        <button class="btn btn-success" @disabled($valides === 0)><i class="bi bi-check-lg me-1"></i>Confirmer l'import ({{ $valides }} ligne(s))</button>
    </form>
    @endcan
    <form action="{{ route('budget.import.nomenclature.annuler') }}" method="POST">
        @csrf
        <button class="btn btn-light"><i class="bi bi-x-lg me-1"></i>Annuler</button>
    </form>
</div>
@endsection
