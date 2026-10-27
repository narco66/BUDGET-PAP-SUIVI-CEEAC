@extends('layouts.app')
@section('titre', 'Journalisation')

@section('contenu')
@use('App\Support\Horodatage')
@php
    $libellesPerimetre = ['etendu' => 'Périmètre étendu', 'unite' => 'Votre unité et vos propres actions', 'propre' => 'Vos propres actions'];
    $nb = fn ($tab, $cle) => $tab[$cle] ?? 0;
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-activity me-2"></i>Journalisation, traçabilité et horodatage</h4>
        <span class="text-muted small">{{ $libellesPerimetre[$stats['perimetre']] }} · depuis le {{ \Illuminate\Support\Carbon::parse($stats['depuis'])->format('d/m/Y') }} · heures à {{ Horodatage::fuseau() }}</span>
    </div>
    <form method="GET" class="d-flex gap-2 align-items-center">
        <input type="date" name="depuis" value="{{ $stats['depuis'] }}" class="form-control form-control-sm">
        <button class="btn btn-sm btn-outline-secondary">Actualiser</button>
        <a href="{{ route('audit.index') }}" class="btn btn-sm btn-primary"><i class="bi bi-list-ul me-1"></i>Journal</a>
    </form>
</div>

<div class="row g-3 mb-3">
    @foreach ([
        ['Événements', $stats['total'], 'text-primary', 'bi-collection'],
        ['Réussis', $nb($stats['par_resultat'], 'succes'), 'text-success', 'bi-check2-circle'],
        ['Refusés / échecs', $stats['refus'] + $nb($stats['par_resultat'], 'echec'), 'text-danger', 'bi-x-octagon'],
        ['Décisions de workflow', $stats['decisions'], '', 'bi-diagram-3'],
        ['Changements de droits', $stats['droits'], 'text-warning', 'bi-person-gear'],
        ['Actes par délégation', $stats['par_delegation'], 'text-primary', 'bi-person-badge'],
    ] as [$lib, $val, $cls, $ico])
        <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted"><i class="bi {{ $ico }} me-1"></i>{{ $lib }}</div>
            <div class="fs-4 fw-bold {{ $cls }}">{{ number_format($val, 0, ',', ' ') }}</div>
        </div></div></div>
    @endforeach
</div>

@if ($stats['dernier_controle'] !== null || $stats['non_scelles'] !== null)
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><i class="bi bi-fingerprint me-1"></i>Dernier contrôle d'intégrité</div>
        @if ($c = $stats['dernier_controle'])
            <span class="badge text-bg-{{ ['conforme' => 'success', 'anomalie' => 'danger'][$c->resultat] ?? 'secondary' }}">{{ $c->resultat }}</span>
            <span class="small">{{ $c->reference }} · {{ Horodatage::local($c->debut_le, 'd/m/Y H:i') }}</span>
        @else <span class="small text-muted">Aucun contrôle exécuté</span> @endif
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><i class="bi bi-link-45deg me-1"></i>Scellement</div>
        <span class="small">{{ $stats['dernier_scellement']?->reference ?? 'Aucun scellement' }} · <strong>{{ $stats['non_scelles'] }}</strong> événement(s) en attente de scellement</span>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><i class="bi bi-bell me-1"></i>Alertes de journalisation ouvertes</div>
        <span class="fs-5 fw-bold {{ $stats['alertes_ouvertes'] ? 'text-danger' : 'text-success' }}">{{ $stats['alertes_ouvertes'] }}</span>
        <a href="{{ route('audit.integrite') }}" class="small ms-2">Intégrité</a>
    </div></div></div>
</div>
@endif

<div class="row g-3">
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold small text-uppercase text-muted">Par catégorie</h6>
        @foreach (\App\Models\JournalAudit::CATEGORIES as $c => $l)
            <div class="d-flex justify-content-between small border-bottom py-1"><a href="{{ route('audit.index', ['categorie' => $c, 'du' => $stats['depuis']]) }}">{{ $l }}</a><span class="fw-semibold">{{ $nb($stats['par_categorie'], $c) }}</span></div>
        @endforeach
        @if ($stats['anterieurs'])
            <div class="d-flex justify-content-between small border-bottom py-1 text-muted" title="Inscrits avant le module : non classés à l'écriture, non réécrits, protégés par le scellement initial"><span>Antérieurs au module</span><span class="fw-semibold">{{ $stats['anterieurs'] }}</span></div>
        @endif
        <h6 class="fw-bold small text-uppercase text-muted mt-3">Par gravité</h6>
        @foreach (\App\Models\JournalAudit::GRAVITES as $c => $l)
            <div class="d-flex justify-content-between small border-bottom py-1"><a href="{{ route('audit.index', ['gravite' => $c, 'du' => $stats['depuis']]) }}">{{ $l }}</a><span class="fw-semibold">{{ $nb($stats['par_gravite'], $c) }}</span></div>
        @endforeach
    </div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold small text-uppercase text-muted">Par module</h6>
        @forelse ($stats['par_module'] as $m => $n)
            <div class="d-flex justify-content-between small border-bottom py-1"><a href="{{ route('audit.index', ['module' => $m, 'du' => $stats['depuis']]) }}">{{ $m }}</a><span class="fw-semibold">{{ $n }}</span></div>
        @empty <p class="small text-muted">Aucun événement.</p> @endforelse
    </div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold small text-uppercase text-muted">Derniers événements</h6>
        @forelse ($stats['derniers'] as $j)
            <div class="small border-bottom py-1">
                <a href="{{ route('audit.show', $j) }}"><code class="small">{{ $j->code_evenement ?? strtoupper($j->action) }}</code></a>
                <div class="text-muted">{{ $j->user_nom ?? 'Système' }} · {{ Horodatage::local($j->instant(), 'd/m/Y H:i:s') }}</div>
            </div>
        @empty <p class="small text-muted">Aucun événement.</p> @endforelse
    </div></div></div>
</div>
@endsection
