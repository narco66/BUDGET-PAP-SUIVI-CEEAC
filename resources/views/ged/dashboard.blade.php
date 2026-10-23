@extends('layouts.app')
@section('titre', 'Tableau de bord GED')

@section('contenu')
@include('ged._nav-module', ['page' => 'dashboard'])

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Gestion documentaire (GED)</h4>
        <span class="text-muted small">Archivage électronique · Versionning · Signature · Conservation</span>
    </div>
    @can('create', \App\Models\Ged\GedDocument::class)
    <a href="{{ route('ged.documents.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-cloud-upload me-1"></i>Déposer un document</a>
    @endcan
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['label' => 'Total documents', 'val' => $stats['total'], 'icon' => 'files', 'color' => 'primary'],
        ['label' => 'Déposés', 'val' => $stats['deposes'], 'icon' => 'inbox', 'color' => 'info'],
        ['label' => 'Validés', 'val' => $stats['valides'], 'icon' => 'check-circle', 'color' => 'success'],
        ['label' => 'Signés', 'val' => $stats['signes'], 'icon' => 'pen', 'color' => 'dark'],
        ['label' => 'Archivés', 'val' => $stats['archives'], 'icon' => 'archive', 'color' => 'secondary'],
        ['label' => 'Confidentiels', 'val' => $stats['confidentiels'], 'icon' => 'shield-lock', 'color' => 'warning'],
        ['label' => 'En validation', 'val' => $stats['en_validation'], 'icon' => 'hourglass', 'color' => 'warning'],
        ['label' => 'Attente signature', 'val' => $stats['en_attente_signature'], 'icon' => 'pen-fill', 'color' => 'danger'],
    ] as $kpi)
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <i class="bi bi-{{ $kpi['icon'] }} fs-4 text-{{ $kpi['color'] }}"></i>
                <div class="fs-4 fw-bold">{{ number_format($kpi['val']) }}</div>
                <div class="small text-muted">{{ $kpi['label'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
    <div class="col-6 col-md-3 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <i class="bi bi-hdd fs-4 text-primary"></i>
                <div class="fs-6 fw-bold">{{ number_format($stats['volume_octets'] / 1048576, 1) }} Mo</div>
                <div class="small text-muted">Volume stockage</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Documents récents</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Référence</th><th>Titre</th><th>Statut</th><th>Date</th></tr></thead>
                    <tbody>
                    @forelse ($stats['recents'] as $doc)
                        <tr>
                            <td><a href="{{ route('ged.documents.show', $doc) }}">{{ $doc->reference }}</a></td>
                            <td>{{ Str::limit($doc->titre, 40) }}</td>
                            <td><span class="badge bg-secondary">{{ $doc->statut?->libelle() }}</span></td>
                            <td class="small">{{ $doc->date_depot?->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted text-center">Aucun document</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Par module source</div>
            <ul class="list-group list-group-flush">
                @forelse ($stats['par_module'] as $mod => $total)
                    <li class="list-group-item d-flex justify-content-between"><span>{{ $mod ?: 'Non rattaché' }}</span><span class="badge bg-primary">{{ $total }}</span></li>
                @empty
                    <li class="list-group-item text-muted">—</li>
                @endforelse
            </ul>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Dernières activités</div>
            <ul class="list-group list-group-flush small">
                @forelse ($stats['dernieres_activites'] as $j)
                    <li class="list-group-item">
                        <strong>{{ $j->action }}</strong>
                        @if($j->document) — <a href="{{ route('ged.documents.show', $j->document) }}">{{ Str::limit($j->document->reference, 30) }}</a>@endif
                        <div class="text-muted">{{ $j->utilisateur?->name }} · {{ $j->created_at->diffForHumans() }}</div>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Aucune activité</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
