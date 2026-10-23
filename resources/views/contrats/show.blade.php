@extends('layouts.app')
@section('titre', 'Contrat '.$contrat->numero)
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp

@section('contenu')
<a href="{{ route('contrats.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Contrats</a>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">{{ $contrat->numero }}</h4>
    @can('contrat.modifier')<a href="{{ route('contrats.edit', $contrat) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold text-primary">Contrat</h6>
        <table class="table table-sm">
            <tr><th class="text-muted" style="width:35%">Objet</th><td>{{ $contrat->objet }}</td></tr>
            <tr><th class="text-muted">Titulaire</th><td>{{ $contrat->titulaire }}</td></tr>
            <tr><th class="text-muted">Type</th><td>{{ \App\Models\Contrat::TYPES[$contrat->type_contrat] }}</td></tr>
            <tr><th class="text-muted">Montant</th><td>{{ $fmt($contrat->montant) }} XAF</td></tr>
            <tr><th class="text-muted">Statut</th><td><span class="badge bg-info-subtle text-dark">{{ \App\Models\Contrat::STATUTS[$contrat->statut] }}</span></td></tr>
            <tr><th class="text-muted">Signature</th><td>{{ optional($contrat->date_signature)->format('d/m/Y') ?? '—' }}</td></tr>
            <tr><th class="text-muted">Période</th><td>{{ optional($contrat->date_debut)->format('d/m/Y') ?? '—' }} → {{ optional($contrat->date_fin)->format('d/m/Y') ?? '—' }}</td></tr>
            <tr><th class="text-muted">Structure</th><td>{{ $contrat->structure?->libelle ?? '—' }}</td></tr>
        </table>
        @if ($contrat->observations)<p class="small text-muted mb-0">{{ $contrat->observations }}</p>@endif
    </div></div></div>

    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold text-primary">Chaîne reliée</h6>
        <ul class="list-unstyled small mb-0">
            <li class="mb-2"><i class="bi bi-clipboard-plus me-1 text-secondary"></i><strong>Besoin :</strong>
                {{ $contrat->engagement?->expressionBesoin?->numero ?? '—' }}</li>
            <li class="mb-2"><i class="bi bi-receipt me-1 text-secondary"></i><strong>Engagement :</strong>
                @if($contrat->engagement)<a href="{{ route('engagements.show', $contrat->engagement) }}">{{ $contrat->engagement->numero }}</a> ({{ $fmt($contrat->engagement->montant_total) }} XAF)@else —@endif</li>
            <li class="mb-2"><i class="bi bi-briefcase me-1 text-secondary"></i><strong>Marché :</strong>
                @if($contrat->marche)<a href="{{ route('marches.show', $contrat->marche) }}">{{ $contrat->marche->numero }}</a>@else —@endif</li>
            <li class="mb-2"><i class="bi bi-file-earmark-text me-1 text-secondary"></i><strong>Contrat :</strong> {{ $contrat->numero }}</li>
        </ul>
    </div></div></div>
</div>
@include('partials._ged_documents', ['attachable' => $contrat, 'type' => 'contrat'])
@endsection
