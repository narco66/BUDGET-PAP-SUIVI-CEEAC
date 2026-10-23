@extends('layouts.app')
@section('titre', 'Aperçu import structure budget')

@section('contenu')
<a href="{{ route('structure-budget.import-export', ['exercice' => $exercice?->id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Import / Export</a>
<h4 class="fw-bold mb-1">Prévisualisation de l'import</h4>
<p class="text-muted small">Fichier : <strong>{{ $fichier }}</strong> — Exercice {{ $exercice?->annee }}. Aucune donnée n'est encore enregistrée.</p>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-2">
        <div class="fs-4 fw-bold">{{ $total }}</div><div class="text-muted small">Lignes lues</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-2">
        <div class="fs-4 fw-bold text-success">{{ $valides }}</div><div class="text-muted small">Valides</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-2">
        <div class="fs-4 fw-bold text-danger">{{ $total - $valides }}</div><div class="text-muted small">Rejetées</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-2">
        <div class="fs-4 fw-bold">{{ count($colonnes_detectees) }}</div><div class="text-muted small">Colonnes</div></div></div></div>
</div>

@if($colonnes_manquantes)
<div class="alert alert-danger"><strong>Colonnes obligatoires manquantes :</strong> {{ implode(', ', $colonnes_manquantes) }}.
    <a href="{{ route('structure-budget.import.modele') }}">Télécharger le modèle</a>.</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Mapping détecté</div>
    <div class="card-body small">
        <span class="text-muted">Colonnes :</span>
        @foreach($colonnes_detectees as $c)<span class="badge bg-light text-dark me-1">{{ $c }}</span>@endforeach
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Aperçu (10 premières lignes)</div>
    <div class="table-responsive"><table class="table table-sm table-bordered mb-0 small">
        <thead class="table-light"><tr>@foreach($colonnes_detectees as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($apercu as $row)
            <tr>@foreach($colonnes_detectees as $col)<td>{{ \Illuminate\Support\Str::limit((string) (((array) $row)[$col] ?? ''), 28) }}</td>@endforeach</tr>
        @empty<tr><td colspan="{{ max(1, count($colonnes_detectees)) }}" class="text-center text-muted py-3">Fichier vide.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>

@if($rejets)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold text-danger">Lignes rejetées (extrait)</div>
    <ul class="list-group list-group-flush small">
        @foreach($rejets as $r)<li class="list-group-item">Ligne {{ $r['ligne'] }} — {{ $r['motif'] }}</li>@endforeach
    </ul>
</div>
@endif

<div class="d-flex gap-2">
    <form method="POST" action="{{ route('structure-budget.import.confirmer') }}">@csrf
        <button class="btn btn-success" @if($valides === 0 || !empty($colonnes_manquantes)) disabled @endif><i class="bi bi-check-lg me-1"></i>Confirmer l'import ({{ $valides }} ligne(s))</button>
    </form>
    <form method="POST" action="{{ route('structure-budget.import.annuler') }}">@csrf
        <button class="btn btn-light">Annuler</button>
    </form>
</div>
@endsection
