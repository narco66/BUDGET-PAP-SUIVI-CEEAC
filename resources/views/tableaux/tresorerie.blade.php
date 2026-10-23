@extends('layouts.app')
@section('titre', 'Trésorerie unifiée')
@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $r = $synthese['recettes'];
    $d = $synthese['depenses'];
    $f = $synthese['flux'];
    $e = $synthese['equilibre'];
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h5 class="mb-0">Trésorerie unifiée</h5>
        <small class="text-muted">Encaissements vs décaissements · exercice {{ $exercice?->annee ?? '—' }}</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
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
        <form method="GET">
            <select name="exercice" class="form-select form-select-sm" style="width:180px;" onchange="this.form.submit()">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>Exercice {{ $ex->annee }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

@if ($e['alerte'])
<div class="alert alert-warning py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i>{{ $e['alerte'] }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm border-start border-3 border-success"><div class="card-body">
        <div class="text-muted small">Encaissements validés</div>
        <div class="fs-5 fw-bold text-success">{{ $fmt($f['encaissements']) }}</div>
        <small class="text-muted">XAF · {{ $r['taux_encaissement'] }}% du réalisé recettes</small>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm border-start border-3 border-danger"><div class="card-body">
        <div class="text-muted small">Décaissements (paiements validés)</div>
        <div class="fs-5 fw-bold text-danger">{{ $fmt($f['decaissements']) }}</div>
        <small class="text-muted">XAF · budget payé {{ $fmt($d['paye']) }}</small>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm border-start border-3 {{ $f['net'] >= 0 ? 'border-primary' : 'border-warning' }}"><div class="card-body">
        <div class="text-muted small">Flux net de trésorerie</div>
        <div class="fs-5 fw-bold {{ $f['net'] >= 0 ? 'text-primary' : 'text-warning' }}">{{ $fmt($f['net']) }}</div>
        <small class="text-muted">Encaissements − décaissements</small>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Couverture des paiements</div>
        <div class="fs-5 fw-bold">{{ $f['couverture_paiements'] !== null ? $f['couverture_paiements'].'%' : '—' }}</div>
        <small class="text-muted">Encaissements / décaissements</small>
    </div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <h6 class="text-muted text-uppercase small mb-2"><i class="bi bi-piggy-bank me-1"></i>Recettes & mobilisation</h6>
        <div class="row g-2">
            <div class="col-6"><div class="card shadow-sm"><div class="card-body py-2">
                <div class="text-muted small">Prévu / réalisé</div>
                <div class="fw-bold">{{ $fmt($r['realise']) }} <span class="text-muted fw-normal">/ {{ $fmt($r['prevu']) }}</span></div>
                <small class="text-muted">{{ $r['taux_realisation'] }}%</small>
            </div></div></div>
            <div class="col-6"><div class="card shadow-sm"><div class="card-body py-2">
                <div class="text-muted small">Contributions recouvrées</div>
                <div class="fw-bold">{{ $fmt($r['recouvre']) }} <span class="text-muted fw-normal">/ {{ $fmt($r['appele']) }}</span></div>
            </div></div></div>
            <div class="col-6"><div class="card shadow-sm border-start border-3 border-warning"><div class="card-body py-2">
                <div class="text-muted small">Reste à recouvrer</div>
                <div class="fw-bold text-warning">{{ $fmt($r['reste_a_recouvrer']) }}</div>
            </div></div></div>
            <div class="col-6"><div class="card shadow-sm border-start border-3 border-danger"><div class="card-body py-2">
                <div class="text-muted small">Arriérés</div>
                <div class="fw-bold text-danger">{{ $fmt($r['arriere']) }}</div>
            </div></div></div>
        </div>
        <div class="mt-2"><a href="{{ route('recettes.index') }}" class="small">Module recettes →</a></div>
    </div>
    <div class="col-lg-6">
        <h6 class="text-muted text-uppercase small mb-2"><i class="bi bi-wallet2 me-1"></i>Dépenses & exécution</h6>
        <div class="row g-2">
            <div class="col-6"><div class="card shadow-sm"><div class="card-body py-2">
                <div class="text-muted small">Crédits ouverts</div>
                <div class="fw-bold">{{ $fmt($d['ouvert']) }}</div>
            </div></div></div>
            <div class="col-6"><div class="card shadow-sm"><div class="card-body py-2">
                <div class="text-muted small">Engagé / ordonnancé</div>
                <div class="fw-bold">{{ $fmt($d['engage']) }} <span class="text-muted fw-normal">/ {{ $fmt($d['ordonnance']) }}</span></div>
            </div></div></div>
            <div class="col-6"><div class="card shadow-sm border-start border-3 border-warning"><div class="card-body py-2">
                <div class="text-muted small">Reste à engager</div>
                <div class="fw-bold text-warning">{{ $fmt($d['reste_a_engager']) }}</div>
            </div></div></div>
            <div class="col-6"><div class="card shadow-sm border-start border-3 border-danger"><div class="card-body py-2">
                <div class="text-muted small">Reste à payer</div>
                <div class="fw-bold text-danger">{{ $fmt($d['reste_a_payer']) }}</div>
                <small class="text-muted">Taux exécution {{ $d['taux_execution'] }}%</small>
            </div></div></div>
        </div>
        <div class="mt-2"><a href="{{ route('depense.index', ['type' => 'paiement']) }}" class="small">Module paiements →</a></div>
    </div>
</div>

@if (count($synthese['evolution_mensuelle']) > 0)
<h6 class="text-muted text-uppercase small mb-2"><i class="bi bi-graph-up me-1"></i>Évolution mensuelle</h6>
<div class="card border-0 shadow-sm mb-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Mois</th>
                    <th class="text-end text-success">Encaissements</th>
                    <th class="text-end text-danger">Décaissements</th>
                    <th class="text-end">Net</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($synthese['evolution_mensuelle'] as $ligne)
                <tr>
                    <td>{{ $ligne['mois'] }}</td>
                    <td class="text-end text-success">{{ $fmt($ligne['entrees']) }}</td>
                    <td class="text-end text-danger">{{ $fmt($ligne['sorties']) }}</td>
                    <td class="text-end fw-semibold {{ $ligne['net'] >= 0 ? 'text-primary' : 'text-warning' }}">{{ $fmt($ligne['net']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<p class="text-muted small">Aucun mouvement de trésorerie comptabilisé sur cet exercice.</p>
@endif

<div class="row g-3">
    <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Exécution titres recette (payé)</div>
        <div class="fs-5 fw-bold text-success">{{ $fmt($synthese['par_sens']['recettes']) }}</div>
    </div></div></div>
    <div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Exécution titres dépense (payé)</div>
        <div class="fs-5 fw-bold text-danger">{{ $fmt($synthese['par_sens']['depenses']) }}</div>
    </div></div></div>
</div>
@endsection
