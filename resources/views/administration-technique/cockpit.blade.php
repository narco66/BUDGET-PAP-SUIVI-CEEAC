@extends('layouts.app')
@section('titre', 'Supervision technique')
@php
    $cls = ['ok' => 'success', 'warning' => 'warning', 'critique' => 'danger'];
    $lib = ['ok' => 'Opérationnel', 'warning' => 'Dégradé', 'critique' => 'Critique'];
    $ico = ['ok' => 'check-circle', 'warning' => 'exclamation-triangle', 'critique' => 'x-octagon'];
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Centre de supervision technique</h4>
        <span class="text-muted small">Santé de la plateforme · health-check natif · /health pour les sondes externes</span>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        @canany(['admin_technique.backup', 'admin_technique.restore'])
        <a href="{{ route('sauvegardes.index') }}" class="btn btn-sm {{ isset($derniereSauvegarde) && $derniereSauvegarde ? 'btn-outline-success' : 'btn-outline-warning' }}"><i class="bi bi-database-check me-1"></i>@if(isset($derniereSauvegarde) && $derniereSauvegarde)Sauvegarde {{ $derniereSauvegarde->created_at->diffForHumans() }}@else Aucune sauvegarde @endif</a>
        @endcanany
        @can('incident.voir')<a href="{{ route('incidents.index') }}" class="btn btn-sm {{ ($incidentsOuverts ?? 0) ? 'btn-outline-danger' : 'btn-outline-secondary' }}"><i class="bi bi-cone-striped me-1"></i>{{ $incidentsOuverts ?? 0 }} incident(s) ouvert(s)@if(($incidentsCritiques ?? 0)) · <span class="text-danger fw-bold">{{ $incidentsCritiques }} critique(s)</span>@endif</a>@endcan
        <span class="badge bg-{{ $cls[$statutGlobal] }} fs-6"><i class="bi bi-{{ $ico[$statutGlobal] }} me-1"></i>{{ $lib[$statutGlobal] ?? $statutGlobal }}</span>
    </div>
</div>

{{-- Contrôles de santé --}}
<div class="row g-3 mb-1">
    @foreach ($controles as $c)
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid var(--bs-{{ $cls[$c['statut']] }}) !important">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted">{{ $c['libelle'] }}</span>
                        <i class="bi bi-{{ $ico[$c['statut']] }} text-{{ $cls[$c['statut']] }}"></i>
                    </div>
                    <div class="fw-bold">{{ $c['valeur'] }}</div>
                    <div class="small text-muted">{{ $c['detail'] }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mt-1">
    {{-- Supervision des modules --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3 me-1"></i>Supervision des modules (volumétrie)</div>
            <div class="card-body p-0"><table class="table table-sm mb-0">
                <tbody>
                @foreach ($supervision as $module => $n)
                    <tr><td class="small">{{ $module }}</td><td class="text-end">@if($n === null)<span class="text-muted small">n/d</span>@else<span class="badge bg-light text-dark">{{ number_format($n, 0, ',', ' ') }}</span>@endif</td></tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    </div>

    {{-- Jobs échoués --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-1">
                <span><i class="bi bi-x-circle me-1"></i>Jobs en échec (file d'attente)</span>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('administration-technique.queues.index') }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-queue-list me-1"></i>Monitoring complet</a>
                    <span class="small text-muted">@if($derniereCapture)Dernière capture : {{ $derniereCapture->capture_le->diffForHumans() }}@endif</span>
                </div>
            </div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>File</th><th>Exception</th><th>Échec</th></tr></thead>
                <tbody>
                @forelse ($jobsEchoues as $j)
                    <tr>
                        <td class="small">{{ $j->queue }}</td>
                        <td class="small text-truncate" style="max-width:340px">{{ \Illuminate\Support\Str::limit($j->exception, 80) }}</td>
                        <td class="small text-muted text-nowrap">{{ \Illuminate\Support\Str::limit((string) $j->failed_at, 16) }}</td>
                    </tr>
                @empty<tr><td colspan="3" class="text-center text-success py-3"><i class="bi bi-check-circle me-1"></i>Aucun job en échec.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>

{{-- Historique des captures --}}
@if($historique->isNotEmpty())
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1"></i>Historique de santé (dernières captures)</div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Capture</th><th>Statut</th><th class="text-end">Disque libre</th><th class="text-end">Jobs attente</th><th class="text-end">Jobs échoués</th><th class="text-end">Échecs connexion 24h</th></tr></thead>
        <tbody>
        @foreach ($historique as $h)
            <tr>
                <td class="small">{{ $h->capture_le->format('d/m/Y H:i') }}</td>
                <td><span class="badge bg-{{ $cls[$h->statut_global] ?? 'secondary' }}">{{ $lib[$h->statut_global] ?? $h->statut_global }}</span></td>
                <td class="text-end small">{{ $h->disque_libre_pct !== null ? $h->disque_libre_pct.'%' : 'n/d' }}</td>
                <td class="text-end small">{{ $h->jobs_en_attente }}</td>
                <td class="text-end small {{ $h->jobs_echoues ? 'text-danger' : '' }}">{{ $h->jobs_echoues }}</td>
                <td class="text-end small">{{ $h->echecs_connexion_24h }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endif
@endsection
