@extends('layouts.app')
@section('titre', 'Tableau de bord')

@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');

    $statutMeta = [
        'brouillon' => ['Brouillon', '#6c757d'],
        'soumis' => ['Soumis', '#0dcaf0'],
        'vise' => ['Visé', '#198754'],
        'rejete' => ['Rejeté', '#dc3545'],
        'annule' => ['Annulé', '#343a40'],
        'paye' => ['Payé', '#0a4a8f'],
    ];
    $engData = $engagements->map(fn ($e) => [
        'label' => $statutMeta[$e->statut][0] ?? ucfirst($e->statut),
        'couleur' => $statutMeta[$e->statut][1] ?? '#adb5bd',
        'nombre' => (int) $e->nombre,
        'montant' => (float) $e->montant,
    ]);

    $nature = $parNature->map(fn ($n) => [
        'x' => $n->code, 'label' => $n->code.' — '.\Illuminate\Support\Str::limit($n->intitule, 24),
        'ouvert' => (float) $n->ouvert, 'paye' => (float) $n->paye,
    ]);

    $pilier = $papParPilier->map(fn ($p) => [
        'x' => $p->code, 'label' => \Illuminate\Support\Str::limit($p->intitule, 28),
        'montant' => (float) $p->montant, 'ceeac' => (float) $p->ceeac, 'ptf' => (float) $p->ptf,
    ]);

    $aucuneDonnee = ($budget['ouvert'] ?? 0) <= 0;
    $tw = fn ($k) => in_array('*', $persona['widgets']) || in_array($k, $persona['widgets']);
@endphp

@section('contenu')
{{-- En-tête --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Tableau de bord</h4>
        <span class="text-muted small">Commission de la CEEAC · Exercice
            <span class="badge bg-primary">{{ $exercice?->annee ?? '—' }}</span>
        </span>
    </div>
    @can('tableau_bord.voir')
        <a href="{{ route('tableaux.index') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-graph-up-arrow me-1"></i>Vue décisionnelle complète
        </a>
    @endcan
</div>

@if ($aucuneDonnee)
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center">
        <i class="bi bi-info-circle fs-4 me-3"></i>
        <div>Aucune donnée budgétaire pour cet exercice. Chargez le jeu de démonstration avec
            <code>php artisan budgetpap:seed-demo --safe</code> ou saisissez un budget.</div>
    </div>
@endif

{{-- Tableaux de bord par persona (profilage selon le rôle) --}}
@if (!empty($persona['cartes']))
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-person-badge me-2"></i>Mes tableaux de bord</span>
        <span class="badge bg-primary-subtle text-primary">Profil : {{ $persona['persona'] }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach ($persona['cartes'] as $carte)
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <a href="{{ route($carte['route']) }}" class="text-decoration-none">
                    <div class="border rounded-3 h-100 p-3 dashboard-persona-tile">
                        <div class="d-flex align-items-center mb-1">
                            <i class="bi {{ $carte['icone'] }} fs-4 text-primary me-2"></i>
                            <span class="fw-semibold text-dark">{{ $carte['label'] }}</span>
                        </div>
                        <div class="small text-muted">{{ $carte['desc'] }}</div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- KPIs principaux --}}
@if ($tw('budget_kpi'))
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body position-relative">
                <div class="text-muted small">Crédits ouverts</div>
                <div class="fs-4 fw-bold text-primary">{{ $fmt($budget['ouvert']) }}</div>
                <small class="text-muted">XAF</small>
                <i class="bi bi-wallet2 text-primary opacity-25 position-absolute end-0 top-0 m-3 fs-3"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Taux d'engagement</div>
                <div class="fs-4 fw-bold text-warning">{{ $budget['taux_engagement'] }}%</div>
                <div class="progress mt-1" style="height:6px;" role="progressbar"
                     aria-valuenow="{{ $budget['taux_engagement'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-warning" style="width:{{ min($budget['taux_engagement'], 100) }}%"></div>
                </div>
                <small class="text-muted">Engagé : {{ $fmt($budget['engage']) }} XAF</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Taux d'exécution (payé)</div>
                <div class="fs-4 fw-bold text-success">{{ $budget['taux_execution'] }}%</div>
                <div class="progress mt-1" style="height:6px;" role="progressbar"
                     aria-valuenow="{{ $budget['taux_execution'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width:{{ min($budget['taux_execution'], 100) }}%"></div>
                </div>
                <small class="text-muted">Payé : {{ $fmt($budget['paye']) }} XAF</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body position-relative">
                <div class="text-muted small">Crédits disponibles</div>
                <div class="fs-4 fw-bold text-info">{{ $fmt($budget['disponible']) }}</div>
                <small class="text-muted">XAF (ouvert − engagé)</small>
                <i class="bi bi-piggy-bank text-info opacity-25 position-absolute end-0 top-0 m-3 fs-3"></i>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Chaîne d'exécution (liquidation → paiement) --}}
@if ($tw('budget_chain'))
<div class="row g-3 mb-3">
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Taux de liquidation</div>
                <div class="fs-5 fw-bold text-info">{{ $budget['taux_liquidation'] ?? 0 }}%</div>
                <small class="text-muted">Liquidé : {{ $fmt($budget['liquide'] ?? 0) }} XAF</small>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Taux d'ordonnancement</div>
                <div class="fs-5 fw-bold text-secondary">{{ $budget['taux_ordonnancement'] ?? 0 }}%</div>
                <small class="text-muted">Ordonnancé : {{ $fmt($budget['ordonnance'] ?? 0) }} XAF</small>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Taux de paiement</div>
                <div class="fs-5 fw-bold text-success">{{ $budget['taux_paiement'] ?? 0 }}%</div>
                <small class="text-muted">Reste à payer : {{ $fmt($budget['reste_a_payer'] ?? 0) }} XAF</small>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Restes budgétaires --}}
@if ($tw('budget_reste'))
<div class="row g-3 mb-3">
    <div class="col-sm-6">
        <div class="card border-0 shadow-sm h-100 border-start border-3 border-warning">
            <div class="card-body">
                <div class="text-muted small">Restes à engager</div>
                <div class="fs-4 fw-bold text-warning">{{ $fmt($budget['reste_a_engager'] ?? 0) }}</div>
                <small class="text-muted">XAF · budget adopté − engagé</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="card border-0 shadow-sm h-100 border-start border-3 border-danger">
            <div class="card-body">
                <div class="text-muted small">Restes à payer</div>
                <div class="fs-4 fw-bold text-danger">{{ $fmt($budget['reste_a_payer'] ?? 0) }}</div>
                <small class="text-muted">XAF · ordonnancé − payé</small>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Mini-statistiques --}}
@if ($tw('pap_summary') || $tw('admin_stats'))
<div class="row g-3 mb-3">
    @if ($tw('admin_stats'))
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="d-flex align-items-center">
                <i class="bi bi-diagram-3 fs-3 text-primary me-2"></i>
                <div><div class="fs-5 fw-bold">{{ $fmt($kpis['structures']) }}</div>
                    <div class="text-muted small">Structures actives</div></div>
            </div>
        </div></div>
    </div>
    @endif
    @if ($tw('admin_stats'))
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="d-flex align-items-center">
                <i class="bi bi-people fs-3 text-success me-2"></i>
                <div><div class="fs-5 fw-bold">{{ $fmt($kpis['utilisateurs']) }}</div>
                    <div class="text-muted small">Utilisateurs actifs</div></div>
            </div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="d-flex align-items-center">
                <i class="bi bi-cash-coin fs-3 text-warning me-2"></i>
                <div><div class="fs-6 fw-bold">{{ $fmt($kpis['contributions']) }}</div>
                    <div class="text-muted small">Contributions appelées (XAF)</div></div>
            </div>
        </div></div>
    </div>
    @endif
    @if ($tw('pap_summary'))
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="d-flex align-items-center">
                <i class="bi bi-bullseye fs-3 text-info me-2"></i>
                <div><div class="fs-5 fw-bold">{{ $fmt($pap['activites']) }}</div>
                    <div class="text-muted small">Activités PAP · phys. {{ $pap['taux_physique'] }}%</div></div>
            </div>
        </div></div>
    </div>
    @endif
</div>
@endif

{{-- Graphiques --}}
@if ($tw('budget_chain') || $tw('feux') || $tw('charts_nature') || $tw('charts_engagements') || $tw('charts_pilier') || $tw('activity_feed'))
<div class="row g-3">
    @if ($tw('budget_chain'))
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart-steps me-1"></i>Chaîne d'exécution budgétaire</h6>
            <div id="chartChaine"></div>
        </div></div>
    </div>
    @endif
    @if ($tw('feux'))
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="bi bi-traffic-light me-1"></i>Performance des indicateurs</h6>
            <div id="chartFeux"></div>
        </div></div>
    </div>
    @endif
    @if ($tw('charts_nature'))
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart me-1"></i>Consommation par nature (ouvert vs payé)</h6>
            <div id="chartNature"></div>
        </div></div>
    </div>
    @endif
    @if ($tw('charts_engagements'))
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="bi bi-clipboard-check me-1"></i>Engagements par statut</h6>
            <div id="chartEngagements"></div>
        </div></div>
    </div>
    @endif
    @if ($tw('charts_pilier'))
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="bi bi-layers me-1"></i>PAP par pilier stratégique (CEEAC vs PTF)</h6>
            <div id="chartPilier"></div>
        </div></div>
    </div>
    @endif
    @if ($tw('activity_feed'))
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-1"></i>Derniers engagements</h6>
            @forelse ($derniersEngagements as $e)
                <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                    <div class="me-2">
                        <div class="small fw-semibold text-truncate" style="max-width:200px;">{{ $e->objet }}</div>
                        <span class="text-muted" style="font-size:.75rem;">{{ $e->numero }} · {{ optional($e->date_engagement)->format('d/m/Y') }}</span>
                    </div>
                    <div class="text-end">
                        <div class="small fw-bold">{{ $fmt($e->montant_total) }}</div>
                        <span class="badge" style="background-color: {{ $statutMeta[$e->statut][1] ?? '#adb5bd' }};">
                            {{ $statutMeta[$e->statut][0] ?? ucfirst($e->statut) }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="text-muted small mb-0">Aucun engagement enregistré.</p>
            @endforelse
        </div></div>
    </div>
    @endif
</div>
@endif
@endsection

@push('scripts')
@vite('resources/js/charts.js')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
// Après exécution des modules Vite (ApexCharts) : un module est différé, ce script ne l'est pas.
document.addEventListener('DOMContentLoaded', function () {
    const nf = new Intl.NumberFormat('fr-FR');
    const xaf = v => nf.format(Math.round(v));
    // Format compact pour les axes : 1 250 000 000 -> « 1,3 Md »
    const compact = v => {
        const a = Math.abs(v);
        if (a >= 1e9) return (v / 1e9).toLocaleString('fr-FR', {maximumFractionDigits: 1}) + ' Md';
        if (a >= 1e6) return (v / 1e6).toLocaleString('fr-FR', {maximumFractionDigits: 0}) + ' M';
        if (a >= 1e3) return (v / 1e3).toLocaleString('fr-FR', {maximumFractionDigits: 0}) + ' k';
        return nf.format(v);
    };
    const base = {
        chart: { fontFamily: 'inherit', toolbar: { show: false }, animations: { easing: 'easeinout', speed: 400 } },
        noData: { text: 'Aucune donnée disponible', style: { color: '#6c757d' } },
        tooltip: { y: { formatter: v => xaf(v) + ' XAF' } },
    };
    const mount = (sel, opts) => {
        const el = document.querySelector(sel);
        if (!el) return;
        try { new ApexCharts(el, opts).render(); }
        catch (e) { console.error('Graphique ' + sel, e); el.innerHTML = '<p class="text-muted small">Graphique indisponible.</p>'; }
    };

    // 1) Chaîne d'exécution budgétaire (barres horizontales)
    mount('#chartChaine', { ...base,
        chart: { ...base.chart, type: 'bar', height: 320 },
        plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4, barHeight: '65%' } },
        series: [{ data: [
            { x: 'Ouvert', y: {{ (int) $budget['ouvert'] }} },
            { x: 'Engagé', y: {{ (int) $budget['engage'] }} },
            { x: 'Liquidé', y: {{ (int) $budget['liquide'] }} },
            { x: 'Ordonnancé', y: {{ (int) $budget['ordonnance'] }} },
            { x: 'Payé', y: {{ (int) $budget['paye'] }} },
        ] }],
        colors: ['#0a4a8f', '#fd7e14', '#0dcaf0', '#6c757d', '#198754'],
        legend: { show: false },
        dataLabels: { enabled: true, formatter: compact, style: { fontSize: '11px' } },
        xaxis: { labels: { formatter: compact } },
    });

    // 2) Performance des indicateurs (donut feux GAR)
    mount('#chartFeux', { ...base,
        chart: { ...base.chart, type: 'donut', height: 300 },
        series: [{{ $feux['vert'] }}, {{ $feux['orange'] }}, {{ $feux['rouge'] }}],
        labels: ['Vert', 'Orange', 'Rouge'],
        colors: ['#198754', '#fd7e14', '#dc3545'],
        legend: { position: 'bottom' },
        tooltip: { y: { formatter: v => nf.format(v) + ' mesure(s)' } },
        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Mesures' } } } } },
    });

    // 3) Consommation par nature (barres ouvert vs payé)
    const nature = @json($nature);
    mount('#chartNature', { ...base,
        chart: { ...base.chart, type: 'bar', height: 320 },
        plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
        series: [
            { name: 'Ouvert', data: nature.map(n => n.ouvert) },
            { name: 'Payé', data: nature.map(n => n.paye) },
        ],
        xaxis: { categories: nature.map(n => n.x) },
        yaxis: { labels: { formatter: compact } },
        colors: ['#0a4a8f', '#198754'],
        dataLabels: { enabled: false },
        legend: { position: 'top' },
        tooltip: { ...base.tooltip, x: { formatter: (_, o) => nature[o.dataPointIndex]?.label ?? '' } },
    });

    // 4) Engagements par statut (donut, nombre)
    const eng = @json($engData);
    mount('#chartEngagements', { ...base,
        chart: { ...base.chart, type: 'donut', height: 320 },
        series: eng.map(e => e.nombre),
        labels: eng.map(e => e.label),
        colors: eng.map(e => e.couleur),
        legend: { position: 'bottom' },
        tooltip: { y: { formatter: (v, o) => nf.format(v) + ' engagement(s) · ' + xaf(eng[o.seriesIndex]?.montant ?? 0) + ' XAF' } },
        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Engagements' } } } } },
    });

    // 5) PAP par pilier (barres empilées CEEAC vs PTF)
    const pilier = @json($pilier);
    mount('#chartPilier', { ...base,
        chart: { ...base.chart, type: 'bar', height: 320, stacked: true },
        plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } },
        series: [
            { name: 'CEEAC', data: pilier.map(p => p.ceeac) },
            { name: 'PTF', data: pilier.map(p => p.ptf) },
        ],
        xaxis: { categories: pilier.map(p => p.x) },
        yaxis: { labels: { formatter: compact } },
        colors: ['#0a4a8f', '#20c997'],
        dataLabels: { enabled: false },
        legend: { position: 'top' },
        tooltip: { ...base.tooltip, x: { formatter: (_, o) => pilier[o.dataPointIndex]?.label ?? '' } },
    });
});
</script>
@endpush
