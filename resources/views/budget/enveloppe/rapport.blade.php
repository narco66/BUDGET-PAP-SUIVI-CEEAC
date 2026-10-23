@extends('layouts.app')
@section('titre', 'Rapport budget · '.($budget?->reference ?? $synthese['exercice']['annee'] ?? ''))

@php
$fmt = fn ($n) => number_format((float)$n, 0, ',', ' ');
$env = $synthese['enveloppe'] ?? null;
$m = $synthese['montants'] ?? [];
$conf = $synthese['conformite'] ?? [];
@endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold mb-0">Rapport budgétaire institutionnel</h5>
        <small class="text-muted">Exercice {{ $synthese['exercice']['annee'] ?? '—' }} · généré {{ now()->format('d/m/Y H:i') }}</small>
    </div>
    <div class="d-flex gap-2">
        @if($budget)
        <a href="{{ route('budget.enveloppe.show', $budget) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i>Enveloppe</a>
        @endif
        @can('rapport.voir')
        @if($synthese['exercice']['id'] ?? null)
        <a href="{{ route('reporting.generer', ['type' => 'budget_institutionnel', 'format' => 'pdf', 'exercice' => $synthese['exercice']['id']]) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>PDF</a>
        <a href="{{ route('reporting.generer', ['type' => 'budget_institutionnel', 'format' => 'excel', 'exercice' => $synthese['exercice']['id']]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Excel</a>
        @endif
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Conformité cycle</div><div class="fs-4 fw-bold text-{{ ($conf['score_pct'] ?? 0) >= 80 ? 'success' : 'warning' }}">{{ $conf['score_pct'] ?? 0 }}%</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Anomalies audit</div><div class="fs-4 fw-bold {{ ($synthese['audit']['anomalies_total'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $synthese['audit']['anomalies_total'] ?? 0 }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Crédits (révisés)</div><div class="fs-5 fw-bold">{{ $fmt($m['revise'] ?? 0) }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">Enveloppe</div><div class="fs-6 fw-semibold">{{ $env['reference'] ?? '—' }} <span class="badge bg-light text-dark">{{ $env['statut'] ?? '' }}</span></div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Checklist cycle B0–B4</div>
            <ul class="list-group list-group-flush">
                @foreach($conf['checks'] ?? [] as $cle => $ok)
                <li class="list-group-item d-flex justify-content-between small">
                    <span>{{ str_replace('_', ' ', $cle) }}</span>
                    <span class="badge bg-{{ $ok ? 'success' : 'secondary' }}">{{ $ok ? 'OK' : '—' }}</span>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold">Phases institutionnelles</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-6">Propositions (B2)</dt><dd class="col-6">{{ $synthese['preparation']['total'] ?? 0 }}</dd>
                    <dt class="col-6">Consolidations (B3)</dt><dd class="col-6">{{ $synthese['consolidations']['count'] ?? 0 }}</dd>
                    <dt class="col-6">Simulations (B3)</dt><dd class="col-6">{{ $synthese['simulations']['count'] ?? 0 }}</dd>
                    <dt class="col-6">Révisions validées (B4)</dt><dd class="col-6">{{ $synthese['revisions']['count'] ?? 0 }}</dd>
                    <dt class="col-6">Pluriannuel</dt><dd class="col-6">{{ $synthese['pluriannuel'] ? 'Rattaché' : 'Non rattaché' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

@if(($synthese['audit']['cycle_institutionnel']['alertes'] ?? []) !== [])
<div class="alert alert-warning border-0 shadow-sm">
    <strong>Alertes cycle :</strong>
    @foreach($synthese['audit']['cycle_institutionnel']['alertes'] as $a)
    <span class="badge bg-warning text-dark me-1">{{ str_replace('_', ' ', $a['code']) }}</span>
    @endforeach
</div>
@endif

<p class="small text-muted mb-0">CLI : <code>php artisan budget:rapport --exercice={{ $synthese['exercice']['annee'] ?? '' }} --json</code></p>
@endsection
