@extends('layouts.app')
@section('titre', 'Recommandation '.$recommandation->numero)

@section('contenu')
<a href="{{ route('recommandations.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Recommandations</a>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">{{ $recommandation->numero }}</h4>
    @can('recommandation.modifier')<a href="{{ route('recommandations.edit', $recommandation) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>

<div class="card border-0 shadow-sm"><div class="card-body">
    <h5>{{ $recommandation->libelle }}</h5>
    @if ($recommandation->description)<p class="text-muted">{{ $recommandation->description }}</p>@endif
    <table class="table table-sm">
        <tr><th class="text-muted" style="width:30%">Source</th><td>{{ \App\Models\Recommandation::SOURCES[$recommandation->source] }}</td></tr>
        <tr><th class="text-muted">Référence réunion</th><td>{{ $recommandation->reference_reunion ?? '—' }}</td></tr>
        <tr><th class="text-muted">Date d'émission</th><td>{{ optional($recommandation->date_emission)->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><th class="text-muted">Échéance</th><td>{{ optional($recommandation->echeance)->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><th class="text-muted">Priorité</th><td>{{ \App\Models\Recommandation::PRIORITES[$recommandation->priorite] }}</td></tr>
        <tr><th class="text-muted">Statut</th><td>{{ \App\Models\Recommandation::STATUTS[$recommandation->statut] }}</td></tr>
        <tr><th class="text-muted">Taux d'exécution</th><td>
            <div class="progress" style="height:18px;max-width:300px"><div class="progress-bar bg-success" style="width:{{ $recommandation->taux_execution }}%">{{ (int) $recommandation->taux_execution }}%</div></div>
        </td></tr>
        <tr><th class="text-muted">Structure responsable</th><td>{{ $recommandation->structure?->libelle ?? '—' }}</td></tr>
        <tr><th class="text-muted">Responsable du suivi</th><td>{{ $recommandation->responsable?->name ?? '—' }}</td></tr>
    </table>
    @if ($recommandation->observations)<p class="small text-muted mb-0"><strong>Observations :</strong> {{ $recommandation->observations }}</p>@endif
</div></div>
@include('partials._ged_documents', ['attachable' => $recommandation, 'type' => 'recommandation'])
@endsection
