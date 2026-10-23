@extends('layouts.app')
@section('titre', 'Cockpit de gouvernance')
@php $rmeta = fn ($n) => \App\Models\Risque::niveauMeta($n); @endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Cockpit gouvernance, contrôle interne & conformité</h4>
        <span class="text-muted small">Conformité par domaine · Risques · Piste d'audit — Exercice {{ $exercice?->annee ?? '—' }}</span>
    </div>
    <form method="GET"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice?->id === $ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
    </select></form>
</div>

{{-- Bandeau synthèse --}}
@php $scoreCls = $scoreGlobal >= 80 ? 'success' : ($scoreGlobal >= 50 ? 'warning' : 'danger'); @endphp
<div class="row g-3 mb-1">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 text-center">
        <div class="display-6 fw-bold text-{{ $scoreCls }}">{{ $scoreGlobal }}</div><div class="text-muted small">Score global de conformité /100</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 text-center">
        <div class="display-6 fw-bold text-danger">{{ ($risques['par_niveau']['critique'] ?? 0) + ($risques['par_niveau']['eleve'] ?? 0) }}</div><div class="text-muted small">Risques élevés / critiques</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 text-center">
        <div class="display-6 fw-bold {{ $audit['sod_violations'] ? 'text-danger' : 'text-success' }}">{{ $audit['sod_violations'] }}</div><div class="text-muted small">Violations séparation des fonctions</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body py-2 text-center">
        <div class="display-6 fw-bold">{{ number_format($audit['evenements'], 0, ',', ' ') }}</div><div class="text-muted small">Événements tracés</div></div></div></div>
</div>

<div class="row g-3 mt-1">
    {{-- Conformité par domaine --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard-check me-1"></i>Conformité par domaine (services d'audit)</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Domaine</th><th class="text-center">Score</th><th class="text-end">Anomalies</th><th class="text-end">Critiques</th></tr></thead>
                <tbody>
                @foreach ($conformite as $d)
                    @php $cls = $d['score'] === null ? 'secondary' : ($d['score'] >= 80 ? 'success' : ($d['score'] >= 50 ? 'warning' : 'danger')); @endphp
                    <tr>
                        <td class="small">{{ $d['domaine'] }}@if($d['erreur']) <span class="text-muted">(n/d)</span>@endif</td>
                        <td class="text-center">@if($d['score'] !== null)<span class="badge bg-{{ $cls }}">{{ $d['score'] }}</span>@else<span class="text-muted">—</span>@endif</td>
                        <td class="text-end small">{{ $d['anomalies'] ?? '—' }}</td>
                        <td class="text-end small {{ ($d['critiques'] ?? 0) > 0 ? 'text-danger fw-semibold' : '' }}">{{ $d['critiques'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-footer bg-white small text-muted">Score = 100 − pénalités d'anomalies (services Dépense & Suivi) ; les autres domaines exposent le décompte d'anomalies.</div>
        </div>
    </div>

    {{-- Risques + piste d'audit --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-exclamation-triangle me-1"></i>Registre des risques</span>
                @can('risques.voir')<a href="{{ route('risques.cartographie') }}" class="small text-decoration-none">Cartographie <i class="bi bi-arrow-right"></i></a>@endcan
            </div>
            <div class="card-body py-2">
                <div class="d-flex justify-content-between small"><span>Total (registre + existants)</span><strong>{{ $risques['total'] }}</strong></div>
                @foreach (['critique' => 'Critique', 'eleve' => 'Élevé', 'modere' => 'Modéré', 'faible' => 'Faible'] as $k => $lib)
                    <div class="d-flex justify-content-between small"><span class="text-{{ $rmeta($k)['classe'] }}">● {{ $lib }}</span><span>{{ $risques['par_niveau'][$k] ?? 0 }}</span></div>
                @endforeach
            </div>
        </div>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-ui-checks-grid me-1"></i>Contrôle interne (COSO) & conformité</span>
                @can('controle_interne.voir')<a href="{{ route('controle-interne.index') }}" class="small text-decoration-none">COSO <i class="bi bi-arrow-right"></i></a>@endcan
            </div>
            <div class="card-body py-2 small">
                <div class="d-flex justify-content-between"><span>Contrôles ({{ $coso['composantes_couvertes'] }}/5 composantes)</span><span><strong class="text-success">{{ $coso['efficaces'] }}</strong> eff. · <strong class="text-danger">{{ $coso['inefficaces'] }}</strong> ineff. · {{ $coso['non_evalues'] }} n/é</span></div>
                <div class="d-flex justify-content-between"><span>Taux de conformité</span><strong class="{{ $conformiteStats['taux_conformite'] >= 80 ? 'text-success' : 'text-warning' }}">{{ $conformiteStats['taux_conformite'] }}%</strong></div>
                <div class="d-flex justify-content-between"><span>Non-conformités ouvertes (dont critiques)</span><span><strong class="{{ $conformiteStats['ouvertes'] ? 'text-warning' : '' }}">{{ $conformiteStats['ouvertes'] }}</strong> (<span class="text-danger">{{ $conformiteStats['critiques_ouvertes'] }}</span>)</span></div>
            </div>
        </div>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-search me-1"></i>Missions d'audit</span>
                @can('audit.voir')<a href="{{ route('audit-missions.index') }}" class="small text-decoration-none">Voir <i class="bi bi-arrow-right"></i></a>@endcan
            </div>
            <div class="card-body py-2 small">
                <div class="d-flex justify-content-between"><span>Ouvertes / Clôturées</span><span><strong class="text-info">{{ $missions['ouvertes'] }}</strong> / <strong class="text-success">{{ $missions['cloturees'] }}</strong></span></div>
                <div class="d-flex justify-content-between"><span>Recommandations en attente</span><strong class="{{ $missions['recommandations_en_attente'] ? 'text-warning' : '' }}">{{ $missions['recommandations_en_attente'] }}</strong></div>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-shield-lock me-1"></i>Piste d'audit</span>
                @can('audit.voir')<a href="{{ route('audit.index') }}" class="small text-decoration-none">Journal <i class="bi bi-arrow-right"></i></a>@endcan
            </div>
            <div class="card-body py-2 small">
                <div class="d-flex justify-content-between"><span>Connexions réussies / échouées</span><span><strong class="text-success">{{ $audit['connexions_ok'] }}</strong> / <strong class="text-danger">{{ $audit['connexions_ko'] }}</strong></span></div>
                <hr class="my-2">
                <div class="text-muted mb-1">Actions les plus fréquentes</div>
                @foreach ($audit['par_action'] as $action => $n)
                    <div class="d-flex justify-content-between"><span class="text-truncate">{{ $action }}</span><span class="badge bg-light text-dark">{{ $n }}</span></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
