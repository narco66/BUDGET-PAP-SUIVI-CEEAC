@extends('layouts.app')
@section('titre', 'Exécution financière PAP — '.$pap->exercice?->annee)

@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $s = $chaine['synthese'];
    $barClass = fn ($t) => $t >= 75 ? 'bg-success' : ($t >= 50 ? 'bg-warning' : 'bg-danger');
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
    <div>
        <h1 class="h4 mb-1">Exécution financière du PAP</h1>
        <p class="text-muted small mb-0">{{ $pap->libelle }} · Exercice {{ $pap->exercice?->annee }}</p>
    </div>
    <div class="d-flex gap-1">
        <a href="{{ route('paps.show', $pap) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i>Fiche PAP</a>
        <a href="{{ route('paps.dashboard', ['pap' => $pap->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-speedometer2 me-1"></i>Pilotage</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Activités</div><div class="fw-bold">{{ $s['activites'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Avec ligne</div><div class="fw-bold">{{ $s['avec_ligne'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Engagées</div><div class="fw-bold">{{ $s['avec_engagement'] }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Budget prévu</div><div class="fw-bold small">{{ $fmt($s['budget_prevu']) }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Payé</div><div class="fw-bold text-success small">{{ $fmt($s['montant_paye']) }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body py-2 text-center"><div class="small text-muted">Taux global</div><div class="fw-bold">{{ $s['budget_prevu'] > 0 ? round($s['montant_paye'] / $s['budget_prevu'] * 100, 1) : 0 }}%</div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-2 me-1"></i>Chaîne dépense par activité</div>
    <p class="small text-muted px-3 pt-2 mb-0">Activité → ligne budgétaire → engagement → liquidation → ordonnancement → paiement</p>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Activité</th>
                    <th>Ligne</th>
                    <th class="text-end">Prévu</th>
                    <th class="text-end">Engagé</th>
                    <th class="text-end">Liquidé</th>
                    <th class="text-end">Ordonnancé</th>
                    <th class="text-end">Payé</th>
                    <th style="min-width:90px">Exécution</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($chaine['activites'] as $a)
                <tr>
                    <td><code>{{ $a['code'] }}</code><div class="small">{{ Str::limit($a['intitule'], 35) }}</div></td>
                    <td class="small">{{ $a['ligne_code'] ?? '—' }}</td>
                    <td class="text-end small">{{ $fmt($a['montant_prevu']) }}</td>
                    <td class="text-end small">{{ $fmt($a['montant_engage']) }}</td>
                    <td class="text-end small">{{ $fmt($a['montant_liquide']) }}</td>
                    <td class="text-end small">{{ $fmt($a['montant_ordonnance']) }}</td>
                    <td class="text-end small fw-semibold text-success">{{ $fmt($a['montant_paye']) }}</td>
                    <td>
                        <div class="progress" style="height:6px"><div class="progress-bar {{ $barClass($a['taux_execution']) }}" style="width:{{ min($a['taux_execution'], 100) }}%"></div></div>
                        <span class="small">{{ $a['taux_execution'] }}%</span>
                    </td>
                    <td>
                        @if($a['nb_engagements'] > 0)
                        <button class="btn btn-sm btn-outline-secondary py-0" type="button" data-bs-toggle="collapse" data-bs-target="#chaine-{{ $a['id'] }}">{{ $a['nb_engagements'] }} eng.</button>
                        @else<span class="text-muted small">—</span>@endif
                    </td>
                </tr>
                @if($a['nb_engagements'] > 0)
                <tr class="collapse" id="chaine-{{ $a['id'] }}">
                    <td colspan="9" class="bg-light small">
                        @foreach($a['engagements'] as $eng)
                        <div class="mb-2 border-start border-3 border-primary ps-2">
                            <strong>{{ $eng['numero'] }}</strong> — {{ Str::limit($eng['objet'], 50) }}
                            <span class="badge bg-secondary">{{ $eng['statut'] }}</span>
                            · {{ $fmt($eng['montant_impute']) }} XAF
                            @foreach($eng['liquidations'] as $liq)
                            <div class="ms-3 mt-1">↳ Liquidation <code>{{ $liq['numero'] }}</code> {{ $fmt($liq['montant']) }} ({{ $liq['statut'] }})
                                @foreach($liq['ordonnancements'] as $ord)
                                <div class="ms-3">↳ Ordonnancement <code>{{ $ord['numero'] }}</code> {{ $fmt($ord['montant']) }}
                                    @foreach($ord['paiements'] as $pay)
                                    <div class="ms-3 text-success">↳ Paiement <code>{{ $pay['numero'] }}</code> {{ $fmt($pay['montant']) }} @if($pay['date'])· {{ $pay['date'] }}@endif</div>
                                    @endforeach
                                </div>
                                @endforeach
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    </td>
                </tr>
                @endif
                @empty
                <tr><td colspan="9" class="text-center text-muted py-3">Aucune activité.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-link-45deg me-1"></i>Alignement programmation stratégique ↔ activités</span>
        @can('gererGar', $pap)
        <form action="{{ route('paps.alignements.sync', $pap) }}" method="POST">@csrf
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Synchroniser</button>
        </form>
        @endcan
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="small text-muted">Activités PAP</div><div class="fs-5 fw-bold">{{ $alignements['activites_total'] }}</div></div>
            <div class="col-md-3"><div class="small text-muted">Liées à un projet</div><div class="fs-5 fw-bold">{{ $alignements['activites_projet'] }}</div></div>
            <div class="col-md-3"><div class="small text-muted">Liaisons actives</div><div class="fs-5 fw-bold text-primary">{{ $alignements['liaisons_count'] }}</div></div>
            <div class="col-md-3"><div class="small text-muted">Taux alignement</div><div class="fs-5 fw-bold">{{ $alignements['taux_alignement'] }}%</div></div>
        </div>
        @if($alignements['liaisons']->isNotEmpty())
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Activité</th><th>Programmation</th><th>Plan</th><th>Mode</th></tr></thead>
                <tbody>
                    @foreach($alignements['liaisons']->take(15) as $l)
                    <tr>
                        <td><code>{{ $l->activite?->code }}</code> {{ Str::limit($l->activite?->intitule, 30) }}</td>
                        <td class="small">{{ $l->programmation?->programmationPluriannuelle?->reference ?? '—' }}</td>
                        <td class="small">{{ $l->programmation?->programmationPluriannuelle?->planStrategique?->intitule ?? '—' }}</td>
                        <td><span class="badge bg-secondary">{{ $l->mode }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-muted small mb-0">Aucune liaison granulaire. Utilisez « Synchroniser » pour rattacher les activités ayant un projet stratégique.</p>
        @endif
    </div>
</div>
@endsection
