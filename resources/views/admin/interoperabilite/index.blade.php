@extends('layouts.app')
@section('titre', 'Interopérabilité API')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-plug me-2"></i>Interopérabilité & API</h4>
</div>
@include('admin.interoperabilite._nav-module', ['page' => 'hub'])

@if (session('succes'))<div class="alert alert-success">{{ session('succes') }}</div>@endif

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Applications actives</div>
        <div class="fs-3 fw-bold">{{ $stats['apps_actives'] }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Applications enregistrées</div>
        <div class="fs-3 fw-bold">{{ $stats['apps_total'] }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Clients API actifs</div>
        <div class="fs-3 fw-bold">{{ $stats['clients_actifs'] }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Appels API (24 h)</div>
        <div class="fs-3 fw-bold">{{ $stats['appels_24h'] }}</div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <p class="mb-2">Portail MOA du module interopérabilité : registre des applications connectées, clés d'accès et journal des échanges.</p>
        <div class="d-flex flex-wrap gap-2">
            @can('api.admin')
            <a href="{{ route('admin.interoperabilite.apps.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-plug me-1"></i>Gérer les applications</a>
            @endcan
            @can('api.view')
            <a href="{{ route('api.docs') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-book me-1"></i>Documentation API</a>
            @endcan
            @can('api.monitor')
            <a href="{{ route('admin.interoperabilite.journal') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-text me-1"></i>Consulter le journal</a>
            <a href="{{ route('admin.interoperabilite.monitoring') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-graph-up me-1"></i>Monitoring</a>
            @endcan
            @can('api.admin')
            <a href="{{ route('admin.interoperabilite.webhooks.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-broadcast me-1"></i>Gérer les webhooks</a>
            @endcan
            @canany(['api.admin', 'api.publish'])
            <a href="{{ route('admin.interoperabilite.exports.inventaire', 'xlsx') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Inventaire API</a>
            @endcanany
            @canany(['api.admin', 'api.monitor'])
            <a href="{{ route('admin.interoperabilite.exports.securite', 'pdf') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shield-check me-1"></i>Rapport sécurité</a>
            @endcanany
        </div>
    </div>
</div>
@endsection
