@extends('layouts.app')
@section('titre', 'Formation '.$formation->numero)
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp

@section('contenu')
<a href="{{ route('formations.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Formations</a>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">{{ $formation->numero }}</h4>
    @can('formation.modifier')<a href="{{ route('formations.edit', $formation) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h5>{{ $formation->intitule }}</h5>
        <table class="table table-sm">
            <tr><th class="text-muted" style="width:35%">Type</th><td>{{ \App\Models\Formation::TYPES[$formation->type] }}</td></tr>
            <tr><th class="text-muted">Thématique</th><td>{{ $formation->thematique ?? '—' }}</td></tr>
            <tr><th class="text-muted">Lieu</th><td>{{ $formation->lieu ?? '—' }}</td></tr>
            <tr><th class="text-muted">Période</th><td>{{ optional($formation->date_debut)->format('d/m/Y') ?? '—' }} → {{ optional($formation->date_fin)->format('d/m/Y') ?? '—' }}</td></tr>
            <tr><th class="text-muted">Coût</th><td>{{ $fmt($formation->cout) }} XAF</td></tr>
            <tr><th class="text-muted">Financement</th><td>{{ $formation->sourceFinancement?->libelle ?? '—' }}</td></tr>
            <tr><th class="text-muted">Statut</th><td><span class="badge bg-{{ $formation->statut === 'realise' ? 'success' : ($formation->statut === 'annule' ? 'danger' : 'secondary') }}">{{ \App\Models\Formation::STATUTS[$formation->statut] }}</span></td></tr>
        </table>
        @if ($formation->impact)<p class="small"><strong>Impact :</strong> {{ $formation->impact }}</p>@endif
    </div></div></div>

    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold text-primary">Participation</h6>
        <div class="row text-center g-2 mb-3">
            <div class="col-4"><div class="border rounded p-2"><div class="fs-4 fw-bold">{{ $formation->nb_participants }}</div><div class="small text-muted">Total</div></div></div>
            <div class="col-4"><div class="border rounded p-2"><div class="fs-4 fw-bold text-primary">{{ $formation->nb_hommes }}</div><div class="small text-muted">Hommes</div></div></div>
            <div class="col-4"><div class="border rounded p-2"><div class="fs-4 fw-bold" style="color:#c2185b">{{ $formation->nb_femmes }}</div><div class="small text-muted">Femmes</div></div></div>
        </div>
        @php $tot = max(1, $formation->nb_hommes + $formation->nb_femmes); @endphp
        <div class="progress" style="height:18px">
            <div class="progress-bar bg-primary" style="width:{{ round($formation->nb_hommes / $tot * 100) }}%">H {{ round($formation->nb_hommes / $tot * 100) }}%</div>
            <div class="progress-bar" style="width:{{ round($formation->nb_femmes / $tot * 100) }}%;background:#c2185b">F {{ round($formation->nb_femmes / $tot * 100) }}%</div>
        </div>
        @if ($formation->niveau_satisfaction !== null)
            <div class="mt-3"><div class="small text-muted">Niveau de satisfaction</div>
                <div class="fs-5 fw-bold text-success">{{ rtrim(rtrim(number_format($formation->niveau_satisfaction, 1, ',', ' '), '0'), ',') }} %</div></div>
        @endif
    </div></div></div>
</div>
@include('partials._ged_documents', ['attachable' => $formation, 'type' => 'formation'])
@endsection
