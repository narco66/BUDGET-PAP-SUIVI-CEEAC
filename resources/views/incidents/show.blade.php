@extends('layouts.app')
@section('titre', 'Incident '.$incident->numero)
@php
    $gc = \App\Models\Incident::graviteClasse($incident->gravite);
    $sc = \App\Models\Incident::statutClasse($incident->statut);
    $next = $incident->prochainStatut();
@endphp

@section('contenu')
<a href="{{ route('incidents.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Incidents</a>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><span class="font-monospace text-primary">{{ $incident->numero }}</span> — {{ $incident->titre }}
        <span class="badge bg-{{ $gc }} align-middle">{{ \App\Models\Incident::GRAVITES[$incident->gravite] ?? $incident->gravite }}</span>
        <span class="badge bg-{{ $sc }} align-middle">{{ \App\Models\Incident::STATUTS[$incident->statut] ?? $incident->statut }}</span>
    </h4>
    @can('incident.modifier')@if(! $incident->estClos())<a href="{{ route('incidents.edit', $incident) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endif @endcan
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body">
        <table class="table table-sm mb-0">
            <tr><th class="text-muted" style="width:30%">Catégorie</th><td>{{ $incident->categorie?->libelle ?? '—' }}</td></tr>
            <tr><th class="text-muted">Source</th><td>{{ \App\Models\Incident::SOURCES[$incident->source] ?? $incident->source }}</td></tr>
            <tr><th class="text-muted">Structure</th><td>{{ $incident->structure?->libelle ?? '—' }}</td></tr>
            <tr><th class="text-muted">Déclarant / Assigné</th><td>{{ $incident->declarant?->name ?? '—' }} / {{ $incident->assigne?->name ?? '—' }}</td></tr>
            <tr><th class="text-muted">Détection / Résolution</th><td>{{ $incident->date_detection?->format('d/m/Y H:i') ?? '—' }} / {{ $incident->date_resolution?->format('d/m/Y H:i') ?? '—' }}</td></tr>
            <tr><th class="text-muted">Description</th><td>{{ $incident->description ?: '—' }}</td></tr>
            <tr><th class="text-muted">Impact</th><td>{{ $incident->impact ?: '—' }}</td></tr>
            @if($incident->cause_racine)<tr><th class="text-muted">Cause racine</th><td>{{ $incident->cause_racine }}</td></tr>@endif
            @if($incident->resolution)<tr><th class="text-muted">Résolution</th><td>{{ $incident->resolution }}</td></tr>@endif
        </table>
    </div></div></div>

    {{-- Pilotage du cycle ITIL --}}
    @if(! $incident->estClos())
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold"><i class="bi bi-arrow-right-circle me-1"></i>Traitement de l'incident</div>
            <div class="card-body">
                @can('incident.modifier')
                @if($next && $next !== 'clos')
                <form method="POST" action="{{ route('incidents.avancer', $incident) }}" class="mb-3">
                    @csrf
                    <input name="observation" class="form-control form-control-sm mb-2" placeholder="Observation (facultatif)">
                    <button class="btn btn-info btn-sm w-100"><i class="bi bi-arrow-right me-1"></i>Passer à : {{ \App\Models\Incident::STATUTS[$next] }}</button>
                </form>
                @endif
                @endcan
                @can('incident.valider')
                <form method="POST" action="{{ route('incidents.cloturer', $incident) }}" class="mb-2" onsubmit="return confirm('Clôturer cet incident ?')">
                    @csrf
                    <textarea name="resolution" class="form-control form-control-sm mb-1" rows="2" placeholder="Résolution *" required></textarea>
                    <input name="cause_racine" class="form-control form-control-sm mb-2" placeholder="Cause racine (facultatif)">
                    <button class="btn btn-success btn-sm w-100"><i class="bi bi-check2-circle me-1"></i>Clôturer</button>
                </form>
                <form method="POST" action="{{ route('incidents.rejeter', $incident) }}" onsubmit="return confirm('Rejeter cet incident ?')">
                    @csrf
                    <div class="input-group input-group-sm">
                        <input name="motif" class="form-control" placeholder="Motif de rejet" required>
                        <button class="btn btn-outline-dark">Rejeter</button>
                    </div>
                </form>
                @endcan
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Journal des actions --}}
<h6 class="fw-bold mt-4 mb-2">Journal des actions ({{ $incident->actions->count() }})</h6>
<div class="card border-0 shadow-sm mb-2"><div class="card-body p-0">
    <ul class="list-group list-group-flush">
        @forelse ($incident->actions as $a)
            <li class="list-group-item">
                <div class="d-flex justify-content-between">
                    <span><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ \App\Models\IncidentAction::TYPES[$a->type] ?? $a->type }}</span> <strong>{{ $a->libelle }}</strong></span>
                    <span class="small text-muted">{{ $a->auteur?->name ?? 'Système' }} · {{ $a->created_at?->format('d/m/Y H:i') }}</span>
                </div>
                @if($a->description)<div class="small text-muted mt-1">{{ $a->description }}</div>@endif
                @if($a->statut_avant !== $a->statut_apres)<div class="small text-muted"><i class="bi bi-arrow-right"></i> {{ \App\Models\Incident::STATUTS[$a->statut_avant] ?? $a->statut_avant }} → {{ \App\Models\Incident::STATUTS[$a->statut_apres] ?? $a->statut_apres }}</div>@endif
            </li>
        @empty<li class="list-group-item text-muted small">Aucune action.</li>@endforelse
    </ul>
</div></div>

@can('incident.modifier')
@if(! $incident->estClos())
<form method="POST" action="{{ route('incidents.actions.store', $incident) }}" class="card border-0 shadow-sm"><div class="card-body row g-2">
    @csrf
    <div class="col-md-3"><select name="type" class="form-select form-select-sm">@foreach(\App\Models\IncidentAction::TYPES as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
    <div class="col-md-5"><input name="libelle" class="form-control form-control-sm" placeholder="Action menée *" required></div>
    <div class="col-md-3"><input name="description" class="form-control form-control-sm" placeholder="Détail"></div>
    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">+</button></div>
</div></form>
@endif
@endcan
@endsection
