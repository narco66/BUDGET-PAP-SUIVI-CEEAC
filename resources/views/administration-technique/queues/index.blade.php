@extends('layouts.app')
@section('titre', 'Files d\'attente')
@php
    $clsAttente = fn ($n) => $n > 500 ? 'danger' : ($n > 50 ? 'warning' : 'success');
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Monitoring des files d'attente</h4>
        <span class="text-muted small">Supervision native · alternative légère à Laravel Horizon · driver {{ $resume['driver'] }}</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('administration-technique.cockpit') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-hdd-network me-1"></i>Cockpit technique</a>
        @canany(['admin_technique.monitor', 'admin_technique.admin'])
            @if($resume['total_echoues'] > 0)
            <form method="POST" action="{{ route('administration-technique.queues.retry-all') }}" class="d-inline" onsubmit="return confirm('Relancer tous les jobs en échec ?')">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Relancer tout ({{ $resume['total_echoues'] }})</button>
            </form>
            @endif
            @can('admin_technique.admin')
            @if($resume['total_echoues'] > 0)
            <form method="POST" action="{{ route('administration-technique.queues.flush') }}" class="d-inline" onsubmit="return confirm('Purger définitivement tous les jobs en échec ?')">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Purger les échecs</button>
            </form>
            @endif
            @endcan
        @endcanany
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2">
                <div class="fs-5 fw-bold text-{{ $clsAttente($resume['total_en_attente']) }}">{{ number_format($resume['total_en_attente'], 0, ',', ' ') }}</div>
                <div class="text-muted small">Jobs en attente</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2">
                <div class="fs-5 fw-bold {{ $resume['total_echoues'] ? 'text-warning' : 'text-success' }}">{{ number_format($resume['total_echoues'], 0, ',', ' ') }}</div>
                <div class="text-muted small">Jobs en échec</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2">
                <div class="fs-5 fw-bold">{{ $resume['lots_actifs'] }}</div>
                <div class="text-muted small">Lots (batches) actifs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2">
                <div class="fs-6 fw-bold font-monospace text-truncate" title="{{ $resume['driver'] }}">{{ $resume['driver'] }}</div>
                <div class="text-muted small">Driver queue</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul me-1"></i>Files nommées</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>File</th><th class="text-end">En attente</th></tr></thead>
                    <tbody>
                    @foreach ($files as $file => $n)
                        <tr>
                            <td class="small font-monospace">
                                <a href="{{ route('administration-technique.queues.index', ['file' => $file]) }}" class="text-decoration-none @if($fileFiltre === $file) fw-bold @endif">{{ $file }}</a>
                                @if(in_array($file, $filesConnues, true))<span class="badge bg-light text-muted ms-1">prioritaire</span>@endif
                            </td>
                            <td class="text-end"><span class="badge bg-{{ $clsAttente($n) }}">{{ $n }}</span></td>
                        </tr>
                    @endforeach
                    @if($files === [])<tr><td colspan="2" class="text-center text-muted py-3">Aucune file.</td></tr>@endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-terminal me-1"></i>Commande worker recommandée</div>
            <div class="card-body">
                <p class="small text-muted mb-2">Production CEEAC — lancer un worker permanent (supervisor/systemd) :</p>
                <pre class="bg-light p-2 rounded small mb-2 font-monospace user-select-all">{{ $resume['commande_worker'] }}</pre>
                <p class="small text-muted mb-0">Planificateur : <code>php artisan schedule:work</code> ou cron <code>* * * * * php artisan schedule:run</code></p>
            </div>
        </div>
    </div>
</div>

@if($resume['peut_lister_jobs'])
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-hourglass-split me-1"></i>Jobs en attente (derniers 25)</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Job</th><th>File</th><th>Tentatives</th><th>Statut</th><th>Créé</th></tr></thead>
            <tbody>
            @forelse ($jobsEnAttente as $j)
                <tr>
                    <td class="small">{{ $j->nom }}</td>
                    <td class="small font-monospace">{{ $j->queue }}</td>
                    <td class="small">{{ $j->attempts }}</td>
                    <td class="small">
                        @if($j->reserve)
                            <span class="badge bg-info">En cours</span>
                        @else
                            <span class="badge bg-secondary">En attente</span>
                        @endif
                    </td>
                    <td class="small text-muted text-nowrap">{{ $j->cree_le }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-success py-3"><i class="bi bi-check-circle me-1"></i>Aucun job en attente.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@else
<div class="alert alert-info small mb-3"><i class="bi bi-info-circle me-1"></i>Driver <strong>{{ $resume['driver'] }}</strong> : seuls les volumes par file sont disponibles (pas de liste détaillée des jobs en attente).</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-x-circle me-1"></i>Jobs en échec
            @if($fileFiltre)
                — file <code>{{ $fileFiltre }}</code>
            @endif
        </span>
        @if($fileFiltre)
        <a href="{{ route('administration-technique.queues.index') }}" class="btn btn-sm btn-outline-secondary py-0">Toutes les files</a>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Job</th><th>File</th><th>Exception</th><th>Échec</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($jobsEchoues as $j)
                <tr>
                    <td class="small">{{ $j->nom }}</td>
                    <td class="small font-monospace">{{ $j->queue }}</td>
                    <td class="small text-truncate" style="max-width:280px">{{ $j->exception_courte }}</td>
                    <td class="small text-muted text-nowrap">{{ \Illuminate\Support\Str::limit((string) $j->failed_at, 16) }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('administration-technique.queues.show', $j->uuid) }}" class="btn btn-sm btn-outline-secondary py-0" title="Détail"><i class="bi bi-eye"></i></a>
                        @canany(['admin_technique.monitor', 'admin_technique.admin'])
                        <form method="POST" action="{{ route('administration-technique.queues.retry', $j->uuid) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm btn-outline-primary py-0" title="Relancer"><i class="bi bi-arrow-repeat"></i></button></form>
                        <form method="POST" action="{{ route('administration-technique.queues.forget', $j->uuid) }}" class="d-inline" onsubmit="return confirm('Supprimer ce job en échec ?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger py-0" title="Supprimer"><i class="bi bi-trash"></i></button></form>
                        @endcanany
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-success py-3"><i class="bi bi-check-circle me-1"></i>Aucun job en échec.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($jobsEchoues->hasPages())<div class="card-footer bg-white">{{ $jobsEchoues->withQueryString()->links() }}</div>@endif
</div>

@if($lots->isNotEmpty())
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-collection me-1"></i>Lots récents (batches)</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Nom</th><th>Total</th><th>En attente</th><th>Échecs</th><th>Statut</th></tr></thead>
            <tbody>
            @foreach ($lots as $lot)
                <tr>
                    <td class="small">{{ $lot->name ?: \Illuminate\Support\Str::limit($lot->id, 12) }}</td>
                    <td class="small">{{ $lot->total_jobs }}</td>
                    <td class="small">{{ $lot->pending_jobs }}</td>
                    <td class="small {{ $lot->failed_jobs ? 'text-danger fw-bold' : '' }}">{{ $lot->failed_jobs }}</td>
                    <td class="small">
                        @if($lot->annule)
                            <span class="badge bg-secondary">Annulé</span>
                        @elseif($lot->termine)
                            <span class="badge bg-success">Terminé</span>
                        @else
                            <span class="badge bg-primary">En cours</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
