@extends('layouts.app')
@section('titre', $mission->numero)

@section('contenu')
<a href="{{ route('suivi-evaluation.missions.index', ['exercice' => $mission->exercice_id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Missions</a>
<h4 class="fw-bold mb-3">{{ $mission->numero }} — {{ $mission->libelle }}</h4>
<div class="row g-3">
    <div class="col-md-8"><div class="card border-0 shadow-sm"><div class="card-body">
        <p>{{ $mission->objectif ?? '—' }}</p>
        <dl class="row small mb-0">
            <dt class="col-3">Type</dt><dd class="col-9">{{ \App\Models\MissionSuivi::TYPES[$mission->type] ?? $mission->type }}</dd>
            <dt class="col-3">Statut</dt><dd class="col-9">{{ \App\Models\MissionSuivi::STATUTS[$mission->statut] ?? $mission->statut }}</dd>
            <dt class="col-3">Période</dt><dd class="col-9">{{ $mission->date_debut?->format('d/m/Y') ?? '—' }} → {{ $mission->date_fin?->format('d/m/Y') ?? '—' }}</dd>
            <dt class="col-3">Score</dt><dd class="col-9">{{ $mission->score_mission !== null ? $mission->score_mission.'%' : '—' }}</dd>
        </dl>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Plans ({{ $mission->plans->count() }})</div><ul class="list-group list-group-flush">@forelse($mission->plans as $p)<li class="list-group-item small"><a href="{{ route('suivi-evaluation.plans.show', $p) }}">{{ $p->numero }}</a> — {{ $p->intitule }}</li>@empty<li class="list-group-item text-muted small">Aucun plan.</li>@endforelse</ul></div></div>
</div>
@endsection
