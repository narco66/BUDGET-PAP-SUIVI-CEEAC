@extends('layouts.app')
@section('titre', 'Alertes institutionnelles')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Alertes institutionnelles</h4>
    @can('alertes.manage')
    <a href="{{ route('alertes.export') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export CSV</a>
    @endcan
</div>

@php $k = $data['kpi']; @endphp
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm border-start border-4 border-warning"><div class="card-body text-center"><div class="text-muted small">Ouvertes</div><div class="h4 mb-0 text-warning">{{ $k['ouvertes'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm border-start border-4 border-danger"><div class="card-body text-center"><div class="text-muted small">Critiques / urgence</div><div class="h4 mb-0 text-danger">{{ $k['critiques'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Résolues (30 j)</div><div class="h4 mb-0 text-success">{{ $k['resolues_30j'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="text-muted small">Détectées aujourd'hui</div><div class="h4 mb-0">{{ $k['aujourdhui'] }}</div></div></div></div>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-0">Module</label>
            <select name="module" class="form-select form-select-sm">
                <option value="">Tous</option>
                @foreach ($data['par_module'] as $m)
                <option value="{{ $m->module }}" @selected(($filtres['module'] ?? '') === $m->module)>{{ strtoupper($m->module) }} ({{ $m->ouvertes }} ouvertes)</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-0">Statut</label>
            <select name="statut" class="form-select form-select-sm">
                <option value="">Tous</option>
                <option value="ouverte" @selected(($filtres['statut'] ?? '') === 'ouverte')>Ouvertes</option>
                <option value="resolue" @selected(($filtres['statut'] ?? '') === 'resolue')>Résolues</option>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filtrer</button></div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Réf.</th><th>Module</th><th>Structure</th><th>Criticité</th><th>Titre</th><th>Statut</th><th>Détectée</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($data['recentes'] as $a)
                <tr>
                    <td class="small"><code>{{ $a->reference }}</code></td>
                    <td><span class="badge bg-secondary">{{ strtoupper($a->module) }}</span></td>
                    <td class="small">{{ $a->structure?->code ? $a->structure->code.' · '.($a->structure->sigle ?: $a->structure->libelle) : '—' }}</td>
                    <td><span class="badge bg-{{ \App\Support\NotificationCriticite::badgeClass($a->criticite) }}">{{ $a->criticite }}</span></td>
                    <td>
                        <div class="fw-semibold">{{ $a->titre }}</div>
                        <div class="small text-muted">{{ \Illuminate\Support\Str::limit($a->message, 80) }}</div>
                    </td>
                    <td>
                        @if($a->statut === 'ouverte')
                        <span class="badge bg-warning text-dark">ouverte</span>
                        @else
                        <span class="badge bg-success">résolue</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $a->detecte_le?->format('d/m/Y H:i') }}</td>
                    <td class="text-end">
                        @if($a->estOuverte() && auth()->user()->can('alertes.resolve'))
                        <form action="{{ route('alertes.resoudre', $a) }}" method="POST" class="d-inline">@csrf
                            <button class="btn btn-sm btn-outline-success" title="Résoudre"><i class="bi bi-check2"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Aucune alerte pour ces critères.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
