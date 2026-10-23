@extends('layouts.app')
@section('titre', 'Marché '.$marche->numero)
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp

@section('contenu')
<a href="{{ route('marches.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Marchés</a>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">{{ $marche->numero }}</h4>
    @can('marche.modifier')<a href="{{ route('marches.edit', $marche) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold text-primary">Marché</h6>
        <table class="table table-sm">
            <tr><th class="text-muted" style="width:35%">Objet</th><td>{{ $marche->objet }}</td></tr>
            <tr><th class="text-muted">Type</th><td>{{ \App\Models\Marche::TYPES[$marche->type_marche] }}</td></tr>
            <tr><th class="text-muted">Mode de passation</th><td>{{ \App\Models\Marche::MODES[$marche->mode_passation] }}</td></tr>
            <tr><th class="text-muted">Montant estimé</th><td>{{ $fmt($marche->montant_estime) }} XAF</td></tr>
            <tr><th class="text-muted">Montant attribué</th><td>{{ $fmt($marche->montant_attribue) }} XAF</td></tr>
            <tr><th class="text-muted">Attributaire</th><td>{{ $marche->attributaire ?? '—' }}</td></tr>
            <tr><th class="text-muted">Statut</th><td><span class="badge bg-info-subtle text-dark">{{ \App\Models\Marche::STATUTS[$marche->statut] }}</span></td></tr>
            <tr><th class="text-muted">Lancement / Attribution</th><td>{{ optional($marche->date_lancement)->format('d/m/Y') ?? '—' }} / {{ optional($marche->date_attribution)->format('d/m/Y') ?? '—' }}</td></tr>
            <tr><th class="text-muted">Structure</th><td>{{ $marche->structure?->libelle ?? '—' }}</td></tr>
        </table>
        @if ($marche->observations)<p class="small text-muted mb-0">{{ $marche->observations }}</p>@endif
    </div></div></div>

    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold text-primary">Chaîne reliée</h6>
        <ul class="list-unstyled small mb-0">
            <li class="mb-2"><i class="bi bi-clipboard-plus me-1 text-secondary"></i><strong>Besoin :</strong>
                {{ $marche->expressionBesoin?->numero ?? $marche->engagement?->expressionBesoin?->numero ?? '—' }}</li>
            <li class="mb-2"><i class="bi bi-receipt me-1 text-secondary"></i><strong>Engagement :</strong>
                @if($marche->engagement)<a href="{{ route('engagements.show', $marche->engagement) }}">{{ $marche->engagement->numero }}</a> ({{ $fmt($marche->engagement->montant_total) }} XAF){{-- --}}@else —@endif</li>
            <li class="mb-2"><i class="bi bi-briefcase me-1 text-secondary"></i><strong>Marché :</strong> {{ $marche->numero }}</li>
            <li class="mb-2"><i class="bi bi-file-earmark-text me-1 text-secondary"></i><strong>Contrat :</strong>
                @if($marche->contrat)<a href="{{ route('contrats.show', $marche->contrat) }}">{{ $marche->contrat->numero }}</a>@else <span class="text-muted">aucun</span>@endif</li>
        </ul>
    </div></div></div>
</div>
@include('partials._ged_documents', ['attachable' => $marche, 'type' => 'marche'])
@endsection
