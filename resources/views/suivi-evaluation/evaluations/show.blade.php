@extends('layouts.app')
@section('titre', 'Évaluation '.$evaluation->periode)

@section('contenu')
<a href="{{ route('suivi-evaluation.index', ['exercice' => $evaluation->exercice_id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Suivi-Évaluation</a>
<h4 class="fw-bold mb-3">{{ $evaluation->periode }} · {{ $evaluation->type }}</h4>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">Score global</div><div class="fs-3 fw-bold text-primary">{{ $evaluation->score_global }}%</div>
        <span class="badge bg-{{ match($evaluation->niveau) { 'satisfaisant' => 'success', 'partiel' => 'warning', default => 'danger' } }}">{{ \App\Models\EvaluationPeriodique::NIVEAUX[$evaluation->niveau] ?? $evaluation->niveau }}</span>
    </div></div></div>
    @foreach(['score_physique' => 'Physique', 'score_iov' => 'IOV', 'score_recommandations' => 'Recommandations', 'score_formations' => 'Formations', 'score_conformite' => 'Conformité'] as $k => $label)
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center">
        <div class="text-muted small">{{ $label }}</div><div class="fs-5 fw-bold">{{ $evaluation->$k }}%</div>
    </div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Métadonnées</div><div class="card-body">
        <dl class="row mb-0 small">
            <dt class="col-4">Exercice</dt><dd class="col-8">{{ $evaluation->exercice?->annee }}</dd>
            <dt class="col-4">Campagne</dt><dd class="col-8">{{ $evaluation->campagne?->libelle ?? '—' }}</dd>
            <dt class="col-4">Généré par</dt><dd class="col-8">{{ $evaluation->generePar?->name ?? '—' }}</dd>
            <dt class="col-4">Généré le</dt><dd class="col-8">{{ $evaluation->genere_le?->format('d/m/Y H:i') }}</dd>
        </dl>
    </div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Recommandations liées ({{ $evaluation->recommandations->count() }})</div><div class="card-body p-0">
        @if($evaluation->recommandations->isEmpty())<p class="text-muted small p-3 mb-0">Aucune recommandation rattachée.</p>
        @else<ul class="list-group list-group-flush">@foreach($evaluation->recommandations as $r)<li class="list-group-item small">{{ $r->numero }} — {{ $r->libelle }}</li>@endforeach</ul>@endif
    </div></div></div>
</div>
@endsection
