@extends('layouts.app')
@section('titre', 'Risque '.$risque->code)
@php $m = \App\Models\Risque::niveauMeta($risque->niveau); @endphp

@section('contenu')
<a href="{{ route('risques.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Registre des risques</a>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><span class="font-monospace text-primary">{{ $risque->code }}</span> — {{ $risque->libelle }}</h4>
    @can('risques.modifier')<a href="{{ route('risques.edit', $risque) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body">
        <table class="table table-sm mb-0">
            <tr><th class="text-muted" style="width:35%">Catégorie</th><td>{{ $risque->categorie?->libelle ?? '—' }}</td></tr>
            <tr><th class="text-muted">Processus / domaine</th><td>{{ $risque->processus ?? '—' }}</td></tr>
            <tr><th class="text-muted">Propriétaire</th><td>{{ $risque->proprietaire?->name ?? '—' }}</td></tr>
            <tr><th class="text-muted">Structure</th><td>{{ $risque->structure?->code ?? '—' }} {{ $risque->structure?->libelle }}</td></tr>
            <tr><th class="text-muted">Exercice</th><td>{{ $risque->exercice?->annee ?? '—' }}</td></tr>
            <tr><th class="text-muted">Statut</th><td>{{ \App\Models\Risque::STATUTS[$risque->statut] ?? $risque->statut }}</td></tr>
            <tr><th class="text-muted">Description</th><td>{{ $risque->description ?: '—' }}</td></tr>
            <tr><th class="text-muted">Mesures de maîtrise</th><td>{{ $risque->mesures_maitrise ?: '—' }}</td></tr>
        </table>
    </div></div></div>

    <div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <h6 class="text-muted">Criticité</h6>
        <div class="display-5 fw-bold text-{{ $m['classe'] }}">{{ $risque->criticite }}</div>
        <span class="badge bg-{{ $m['classe'] }} fs-6">{{ $m['libelle'] }}</span>
        <hr>
        <div class="row text-center">
            <div class="col"><div class="fs-5 fw-bold">{{ $risque->probabilite }}</div><div class="small text-muted">Probabilité<br>{{ \App\Models\Risque::PROBABILITES[$risque->probabilite] ?? '' }}</div></div>
            <div class="col"><div class="fs-5 fw-bold">{{ $risque->impact }}</div><div class="small text-muted">Impact<br>{{ \App\Models\Risque::IMPACTS[$risque->impact] ?? '' }}</div></div>
        </div>
        <div class="small text-muted mt-2">Criticité = Probabilité × Impact</div>
    </div></div></div>
</div>
@endsection
