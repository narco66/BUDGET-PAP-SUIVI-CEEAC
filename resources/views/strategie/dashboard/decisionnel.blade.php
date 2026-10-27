@extends('layouts.app')

@section('titre', 'Tableau de pilotage décisionnel')

@php
    $t = $tableau;
    $hub = $t['hub'];
    $rec = $t['reconciliation'];
    $exe = $rec['execution_pap'] ?? [];
    $f = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $c = function ($n) use ($f) {
        $a = abs((float) $n);

        return $a >= 1e9 ? number_format($n / 1e9, 1, ',', ' ').' Md' : ($a >= 1e6 ? number_format($n / 1e6, 1, ',', ' ').' M' : $f($n));
    };
    $pct = fn ($v) => $v === null ? null : number_format((float) $v, 1, ',', ' ').' %';
    $couleur = fn ($v) => $v === null ? 'secondary' : ($v >= 75 ? 'success' : ($v >= 40 ? 'warning' : 'danger'));
    $detail = collect($t['score_pilotage_detail'] ?? []);
    $tauxDe = fn (string $libelle) => $detail->firstWhere('libelle', $libelle)['taux'] ?? null;
    $coherence = ($t['coherence']['score_evaluable'] ?? true) ? (float) $t['coherence']['score_global'] : null;
    $score = ($t['score_pilotage_evaluable'] ?? true) ? (float) $t['score_pilotage'] : null;
    $niveaux = ['danger' => ['danger', 'bi-x-octagon'], 'warning' => ['warning', 'bi-exclamation-triangle'], 'info' => ['info', 'bi-info-circle']];
@endphp

@push('styles')<style>
    .pd-kpi .libelle { font-size: .75rem; color: var(--bs-secondary-color); text-transform: uppercase; letter-spacing: .03em; }
    .pd-kpi .valeur { font-size: 1.5rem; font-weight: 700; line-height: 1.15; font-variant-numeric: tabular-nums; }
    .pd-num { font-variant-numeric: tabular-nums; }
    .pd-maillon { min-width: 110px; }
</style>@endpush

@section('contenu')

@include('strategie._nav', ['active' => 'dashboard'])

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0">Tableau de pilotage décisionnel</h4>
        <span class="text-muted small">Alignement stratégie ↔ PAP ↔ exécution · exercice {{ $t['exercice']?->annee ?? '—' }} · calculé à {{ \App\Support\Horodatage::local(now(), 'H:i') }}</span>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <label for="pd-exercice" class="small text-muted mb-0">Exercice</label>
            <select id="pd-exercice" name="exercice" class="form-select form-select-sm" style="max-width:120px;" onchange="this.form.submit()">
                @foreach($exercices as $ex)<option value="{{ $ex->id }}" @selected($exerciceId == $ex->id)>{{ $ex->annee }}</option>@endforeach
            </select>
        </form>
        @can('strategie.export')
        <a href="{{ route('strategie.export.pilotage.pdf', ['exercice' => $exerciceId]) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>Export PDF</a>
        @endcan
    </div>
</div>

{{-- Référentiel vide : guider la constitution au lieu d'afficher des taux sans base --}}
@if ($t['referentiel_vide'] ?? false)
<div class="card border-0 shadow-sm mb-3 border-start border-4 border-primary">
    <div class="card-body">
        <h6 class="fw-bold mb-1"><i class="bi bi-signpost-2 me-1"></i>Référentiel stratégique à constituer</h6>
        <p class="small text-muted mb-3">Aucune vision, aucun plan stratégique, programme, projet ni objectif n'est encore enregistré : les indicateurs d'alignement ne sont pas évaluables. Le PAP {{ $t['exercice']?->annee }} et son exécution restent suivis ci-dessous. Étapes recommandées :</p>
        <div class="row g-2 small">
            @foreach ([
                ['1', 'Vision', $hub['visions_actives'] ?? 0, 'strategie.visions.create', 'strategie.visions.index'],
                ['2', 'Plan stratégique', $hub['plans_actifs'] ?? 0, 'strategie.plans.create', 'strategie.plans.index'],
                ['3', 'Orientations', $hub['orientations'] ?? 0, 'strategie.orientations.create', 'strategie.orientations.index'],
                ['4', 'Programmes', $hub['programmes'] ?? 0, 'strategie.programmes.create', 'strategie.programmes.index'],
                ['5', 'Projets', $hub['projets'] ?? 0, 'strategie.projets.create', 'strategie.projets.index'],
                ['6', 'Objectifs et indicateurs', ($hub['objectifs'] ?? 0) + ($hub['indicateurs'] ?? 0), 'strategie.objectifs.create', 'strategie.objectifs.index'],
                ['7', 'Rattacher le PAP (piliers, activités)', null, null, 'strategie.pont-pap'],
            ] as [$n, $lib, $nb, $creer, $liste])
            <div class="col-md-6 col-xl-3">
                <div class="d-flex justify-content-between align-items-center p-2 rounded bg-body-tertiary h-100">
                    <span><span class="badge bg-primary rounded-pill me-1">{{ $n }}</span>{{ $lib }}@if($nb !== null) <span class="text-muted">({{ $nb }})</span>@endif</span>
                    <span class="text-nowrap">
                        <a href="{{ route($liste, $liste === 'strategie.pont-pap' ? ['exercice' => $exerciceId] : []) }}" class="small">Ouvrir</a>
                        @if ($creer)· <a href="{{ route($creer) }}" class="small">Créer</a>@endif
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Indicateurs de pilotage --}}
<div class="row g-3 mb-3">
    @foreach ([
        ['Score de pilotage', $score, 'moyenne des composantes évaluables', 'bi-speedometer'],
        ['Cohérence du référentiel', $coherence, 'objectifs, indicateurs, risques, matrice', 'bi-check2-square'],
        ["Matrice d'alignement", $tauxDe("Matrice d'alignement complète"), ($t['alignement']['lignes_matrice'] ?? 0).' ligne(s) analysée(s)', 'bi-grid-3x3'],
        ['Programmation ↔ PAP', $tauxDe('Programmations annuelles liées au PAP'), ($rec['programmations_liees'] ?? 0).' / '.($rec['programmations_total'] ?? 0).' programmation(s) liée(s)', 'bi-link-45deg'],
    ] as [$lib, $val, $sous, $icone])
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100 border-start border-4 border-{{ $couleur($val) }} pd-kpi"><div class="card-body py-2">
        <div class="libelle"><i class="bi {{ $icone }} me-1"></i>{{ $lib }}</div>
        @if ($val === null)
            <div class="valeur text-muted" style="font-size:1.1rem" title="Aucun élément à mesurer">non évaluable</div>
        @else
            <div class="valeur text-{{ $couleur($val) }}">{{ $pct($val) }}</div>
            <div class="progress my-1" style="height:5px" role="progressbar" aria-label="{{ $lib }}" aria-valuenow="{{ min(100, $val) }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-{{ $couleur($val) }}" style="width: {{ min(100, $val) }}%"></div></div>
        @endif
        <div class="small text-muted">{{ $sous }}</div>
    </div></div></div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    {{-- Alertes avec action corrective --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-bell me-1"></i>Points d'attention</div>
            <ul class="list-group list-group-flush small">
                @forelse ($t['alertes'] as $a)
                    @php [$cl, $ic] = $niveaux[$a['niveau'] ?? 'warning'] ?? $niveaux['warning']; @endphp
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                        <span><i class="bi {{ $ic }} text-{{ $cl }} me-1"></i>{{ is_array($a) ? $a['message'] : $a }}</span>
                        @if (is_array($a) && $a['lien'])<a href="{{ $a['lien'] }}" class="btn btn-sm btn-outline-{{ $cl }} text-nowrap py-0">{{ $a['action'] }}</a>@endif
                    </li>
                @empty
                    <li class="list-group-item text-success"><i class="bi bi-check2-circle me-1"></i>Aucun point d'attention.</li>
                @endforelse
            </ul>
        </div>
    </div>
    {{-- Décomposition du score --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-1"></i>Composantes du score de pilotage</div>
            <ul class="list-group list-group-flush small">
                @foreach ($detail as $comp)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span>{{ $comp['libelle'] }} <span class="text-muted">· {{ $comp['base'] }}</span></span>
                            <strong class="pd-num text-{{ $couleur($comp['taux']) }}">{{ $pct($comp['taux']) ?? 'non évaluable' }}</strong>
                        </div>
                        @if ($comp['taux'] !== null)
                        <div class="progress mt-1" style="height:4px" role="presentation"><div class="progress-bar bg-{{ $couleur($comp['taux']) }}" style="width: {{ min(100, $comp['taux']) }}%"></div></div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Exécution du PAP --}}
    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between">
            <span><i class="bi bi-clipboard-data me-1"></i>Exécution du PAP {{ $t['exercice']?->annee ?? '—' }}</span>
            @if ($rec['pap'])@include('strategie._statut', ['statut' => $rec['pap']->statut])@endif
        </div>
        <div class="card-body small">
            @if ($rec['pap'])
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Activités suivies</span><strong class="pd-num">{{ $f($exe['activites'] ?? 0) }}</strong></div>
                @foreach ([['Réalisation physique', $exe['taux_physique'] ?? 0], ['Réalisation financière', $exe['taux_financier'] ?? 0]] as [$lib, $v])
                    <div class="d-flex justify-content-between"><span class="text-muted">{{ $lib }}</span><strong class="pd-num">{{ $pct($v) }}</strong></div>
                    <div class="progress mb-2" style="height:6px" role="progressbar" aria-label="{{ $lib }}" aria-valuenow="{{ min(100, $v) }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-{{ $couleur($v) }}" style="width: {{ max(min(100, $v), $v > 0 ? 1 : 0) }}%"></div></div>
                @endforeach
                <div class="d-flex justify-content-between"><span class="text-muted">Budget prévu</span><strong class="pd-num" title="{{ $f($exe['montant_prevu'] ?? 0) }} XAF">{{ $c($exe['montant_prevu'] ?? 0) }} XAF</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Payé</span><strong class="pd-num" title="{{ $f($exe['montant_paye'] ?? 0) }} XAF">{{ $c($exe['montant_paye'] ?? 0) }} XAF</strong></div>
                <a href="{{ route('paps.index') }}" class="btn btn-sm btn-outline-primary mt-2">Ouvrir le PAP</a>
            @else
                <p class="text-muted mb-0">Aucun PAP pour cet exercice.</p>
            @endif
        </div>
    </div></div>

    {{-- Chaîne stratégique → opérationnelle --}}
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3 me-1"></i>Chaîne stratégique → opérationnelle</div>
        <div class="card-body small">
            <div class="d-flex flex-wrap align-items-center gap-1 mb-3">
                @foreach ([['Visions', $hub['visions_actives'] ?? 0], ['Plans actifs', $hub['plans_actifs'] ?? 0], ['Orientations', $hub['orientations'] ?? 0], ['Programmes', $hub['programmes'] ?? 0], ['Projets', $hub['projets'] ?? 0], ['Activités PAP', $exe['activites'] ?? 0]] as $i => [$lib, $nb])
                    @if ($i)<i class="bi bi-chevron-right text-muted"></i>@endif
                    <div class="text-center px-2 py-1 rounded pd-maillon {{ $nb ? 'bg-primary-subtle' : 'bg-body-tertiary text-muted' }}">
                        <div class="fw-bold fs-6 pd-num">{{ $f($nb) }}</div><div style="font-size:.72rem">{{ $lib }}</div>
                    </div>
                @endforeach
            </div>
            <div class="row g-2">
                <div class="col-sm-6"><div class="p-2 rounded bg-body-tertiary"><div class="text-muted">Piliers PAP orientés</div><strong class="pd-num">{{ $pct($rec['piliers_orientes_pct'] ?? 0) }}</strong></div></div>
                <div class="col-sm-6"><div class="p-2 rounded bg-body-tertiary"><div class="text-muted">Activités rattachées à un projet</div><strong class="pd-num">{{ $pct($rec['activites_projet_pct'] ?? 0) }}</strong></div></div>
                <div class="col-sm-6"><div class="p-2 rounded bg-body-tertiary"><div class="text-muted">Budget des projets stratégiques</div><strong class="pd-num">{{ $c($rec['budget_projets_strategiques'] ?? 0) }} XAF</strong></div></div>
                <div class="col-sm-6"><div class="p-2 rounded bg-body-tertiary"><div class="text-muted">Écart projets − PAP</div>
                    <strong class="pd-num {{ ($rec['ecart_budget_pap'] ?? 0) < 0 ? 'text-danger' : '' }}" title="{{ $f($rec['ecart_budget_pap'] ?? 0) }} XAF">{{ $c($rec['ecart_budget_pap'] ?? 0) }} XAF</strong></div></div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <a href="{{ route('strategie.pont-pap', ['exercice' => $exerciceId]) }}" class="btn btn-sm btn-outline-primary">Pont PAP</a>
                <a href="{{ route('strategie.matrice') }}" class="btn btn-sm btn-outline-secondary">Matrice d'alignement</a>
                <a href="{{ route('strategie.rapport.coherence') }}" class="btn btn-sm btn-outline-secondary">Rapport de cohérence</a>
            </div>
        </div>
    </div></div>
</div>

<div class="row g-3">
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Plans stratégiques actifs</div>
        <ul class="list-group list-group-flush small">@forelse ($t['plans_actifs'] as $p)
            <li class="list-group-item"><code>{{ $p->reference }}</code> {{ Str::limit($p->intitule, 50) }}</li>
        @empty<li class="list-group-item text-muted">Aucun plan actif.</li>@endforelse</ul></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Principaux programmes (budget)</div>
        <ul class="list-group list-group-flush small">@forelse ($t['top_programmes'] as $p)
            <li class="list-group-item d-flex justify-content-between gap-2"><span><code>{{ $p->code }}</code> {{ Str::limit($p->intitule, 35) }}</span><span class="pd-num text-nowrap" title="{{ $f($p->budget_previsionnel) }} XAF">{{ $c($p->budget_previsionnel) }}</span></li>
        @empty<li class="list-group-item text-muted">Aucun programme.</li>@endforelse</ul></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-header bg-white fw-semibold">Principaux projets (budget et engagement)</div>
        <ul class="list-group list-group-flush small">@forelse ($t['top_projets'] as $p)
            @php $taux = $p->budget_previsionnel > 0 ? min(100, round($p->budget_engage / $p->budget_previsionnel * 100, 1)) : null; @endphp
            <li class="list-group-item">
                <div class="d-flex justify-content-between gap-2"><span><code>{{ $p->code }}</code> {{ Str::limit($p->intitule, 30) }}</span><span class="pd-num text-nowrap" title="{{ $f($p->budget_previsionnel) }} XAF">{{ $c($p->budget_previsionnel) }}</span></div>
                @if ($taux !== null)<div class="progress mt-1" style="height:4px" title="Engagé : {{ $pct($taux) }}"><div class="progress-bar bg-{{ $couleur($taux) }}" style="width: {{ $taux }}%"></div></div>@endif
            </li>
        @empty<li class="list-group-item text-muted">Aucun projet.</li>@endforelse</ul></div></div>
</div>
@endsection
