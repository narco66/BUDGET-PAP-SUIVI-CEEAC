@extends('layouts.app')
@section('titre', 'Trésorerie unifiée')
@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $r = $synthese['recettes'];
    $d = $synthese['depenses'];
    $f = $synthese['flux'];
    $e = $synthese['equilibre'];
    $pct = fn ($v, $base) => (float) $base > 0 ? min(round((float) $v / (float) $base * 100, 1), 100) : 0;
    $maxMois = max(1, ...array_map(fn ($l) => max((float) $l['entrees'], (float) $l['sorties']), $synthese['evolution_mensuelle'] ?: [['entrees' => 0, 'sorties' => 0]]));
@endphp

@push('styles')<style>.cd-mt{font-variant-numeric:tabular-nums}.cd-step{border-left:4px solid #0a4a8f !important}.cd-barre{height:.45rem}</style>@endpush

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Trésorerie unifiée</h4>
        <span class="text-muted small">Encaissements → Décaissements → Flux net · Exercice {{ $exercice?->annee ?? '—' }}</span>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        @can('rapport.exporter')
        <a href="{{ route('reporting.generer', ['type' => 'tresorerie_flux', 'exercice' => $exercice?->id, 'format' => 'pdf']) }}" class="btn btn-sm btn-outline-primary" title="Exporter le flux de trésorerie">
            <i class="bi bi-file-earmark-pdf me-1"></i>Flux PDF
        </a>
        <a href="{{ route('reporting.generer', ['type' => 'tresorerie_dette_fournisseurs', 'exercice' => $exercice?->id, 'format' => 'pdf']) }}" class="btn btn-sm btn-outline-danger" title="Exporter la dette fournisseurs">
            <i class="bi bi-file-earmark-pdf me-1"></i>Dette PDF
        </a>
        @endcan
        <a href="{{ route('tableaux.index', ['exercice' => $exercice?->id]) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-bar-chart-line me-1"></i>Tableaux de bord
        </a>
        <form method="GET"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
        </select></form>
    </div>
</div>

@if ($e['alerte'])
<div class="alert alert-warning py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i>{{ $e['alerte'] }}</div>
@endif

{{-- KPIs --}}
<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm cd-step h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold text-success cd-mt">{{ $fmt($f['encaissements']) }}</div>
        <div class="text-muted small">Encaissements validés (XAF) · {{ $r['taux_encaissement'] }}% du réalisé</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm cd-step h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold text-danger cd-mt">{{ $fmt($f['decaissements']) }}</div>
        <div class="text-muted small">Décaissements (paiements validés)</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm cd-step h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold cd-mt {{ $f['net'] >= 0 ? 'text-primary' : 'text-warning' }}">{{ $fmt($f['net']) }}</div>
        <div class="text-muted small">Flux net · encaissements − décaissements</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm cd-step h-100"><div class="card-body py-2">
        <div class="fs-5 fw-bold cd-mt">{{ $f['couverture_paiements'] !== null ? $f['couverture_paiements'].'%' : '—' }}</div>
        <div class="text-muted small">Couverture des paiements</div></div></div></div>
</div>

<div class="row g-3 mt-1">
    {{-- Recettes & mobilisation --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-piggy-bank me-1"></i>Recettes & mobilisation</span>
                <a href="{{ route('recettes.index') }}" class="small fw-normal">Module recettes →</a>
            </div>
            <div class="card-body">
                @foreach ([['Réalisé / prévu', $r['realise'], $r['prevu'], 'bg-success'], ['Recouvré / appelé', $r['recouvre'], $r['appele'], 'bg-info']] as [$lib, $val, $base, $cls])
                <div class="d-flex justify-content-between small mb-1"><span>{{ $lib }}</span><span class="cd-mt">{{ $fmt($val) }} <span class="text-muted">/ {{ $fmt($base) }}</span></span></div>
                <div class="progress mb-3" style="height:1.1rem"><div class="progress-bar {{ $cls }}" style="width:{{ $pct($val, $base) }}%">{{ $pct($val, $base) }}%</div></div>
                @endforeach
                <div class="row text-center mt-2">
                    <div class="col"><div class="fw-bold text-warning cd-mt">{{ $fmt($r['reste_a_recouvrer']) }}</div><div class="text-muted small">Reste à recouvrer</div></div>
                    <div class="col"><div class="fw-bold text-danger cd-mt">{{ $fmt($r['arriere']) }}</div><div class="text-muted small">Arriérés</div></div>
                    <div class="col"><div class="fw-bold cd-mt">{{ $r['taux_realisation'] }}%</div><div class="text-muted small">Taux de réalisation</div></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Dépenses & exécution --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-wallet2 me-1"></i>Dépenses & exécution <span class="text-muted fw-normal small">(% des crédits ouverts)</span></span>
                <a href="{{ route('depense.index', ['type' => 'paiement']) }}" class="small fw-normal">Module paiements →</a>
            </div>
            <div class="card-body">
                @foreach ([['Engagé', $d['engage'], 'bg-primary'], ['Ordonnancé', $d['ordonnance'], 'bg-warning'], ['Payé', $d['paye'], 'bg-success']] as [$lib, $val, $cls])
                <div class="d-flex justify-content-between small mb-1"><span>{{ $lib }}</span><span class="cd-mt">{{ $fmt($val) }}</span></div>
                <div class="progress mb-3" style="height:1.1rem"><div class="progress-bar {{ $cls }}" style="width:{{ $pct($val, $d['ouvert']) }}%">{{ $pct($val, $d['ouvert']) }}%</div></div>
                @endforeach
                <div class="row text-center mt-2">
                    <div class="col"><div class="fw-bold cd-mt">{{ $fmt($d['ouvert']) }}</div><div class="text-muted small">Crédits ouverts</div></div>
                    <div class="col"><div class="fw-bold text-warning cd-mt">{{ $fmt($d['reste_a_engager']) }}</div><div class="text-muted small">Reste à engager</div></div>
                    <div class="col"><div class="fw-bold text-danger cd-mt">{{ $fmt($d['reste_a_payer']) }}</div><div class="text-muted small">Reste à payer · exéc. {{ $d['taux_execution'] }}%</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Évolution mensuelle --}}
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up me-1"></i>Évolution mensuelle des flux</div>
    @if (count($synthese['evolution_mensuelle']) > 0)
    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>Mois</th><th class="text-end">Encaissements</th><th class="text-end">Décaissements</th><th class="text-end">Net</th><th style="width:28%">Entrées / sorties</th>
        </tr></thead>
        <tbody>
        @foreach ($synthese['evolution_mensuelle'] as $ligne)
            <tr>
                <td class="small">{{ $ligne['mois'] }}</td>
                <td class="text-end small text-success cd-mt">{{ $fmt($ligne['entrees']) }}</td>
                <td class="text-end small text-danger cd-mt">{{ $fmt($ligne['sorties']) }}</td>
                <td class="text-end small fw-semibold cd-mt {{ $ligne['net'] >= 0 ? 'text-primary' : 'text-warning' }}">{{ $fmt($ligne['net']) }}</td>
                <td>
                    <div class="progress cd-barre mb-1"><div class="progress-bar bg-success" style="width:{{ $pct($ligne['entrees'], $maxMois) }}%"></div></div>
                    <div class="progress cd-barre"><div class="progress-bar bg-danger" style="width:{{ $pct($ligne['sorties'], $maxMois) }}%"></div></div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @else
    <div class="card-body text-center text-muted py-4 small">Aucun mouvement de trésorerie comptabilisé sur cet exercice.</div>
    @endif
</div>

{{-- Exécution par sens --}}
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-arrow-left-right me-1"></i>Exécution des titres budgétaires (payé)</div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col"><div class="fs-5 fw-bold text-success cd-mt">{{ $fmt($synthese['par_sens']['recettes']) }}</div><div class="text-muted small">Titres de recette</div></div>
            <div class="col"><div class="fs-5 fw-bold text-danger cd-mt">{{ $fmt($synthese['par_sens']['depenses']) }}</div><div class="text-muted small">Titres de dépense</div></div>
        </div>
    </div>
</div>
@endsection
