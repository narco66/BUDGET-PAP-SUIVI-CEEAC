@extends('layouts.app')
@section('titre', 'Reporting & Analyse')

@php
    // Catalogue du menu Reporting : implémentés (route) ou Phase 2 (à venir)
    $catalogue = [
        ['cle' => 'pap',           'libelle' => 'Rapports PAP',            'icone' => 'bullseye',            'desc' => 'Performance du Plan Annuel de Performance'],
        ['cle' => 'execution_synthese', 'libelle' => 'Synthèse exécution budgétaire', 'icone' => 'pie-chart', 'desc' => 'Vue globale nature + structure (ex-rapports synthèse)'],
        ['cle' => 'execution_axe', 'libelle' => 'Rapports Budgétaires',    'icone' => 'cash-stack',          'desc' => 'Exécution budgétaire par axe stratégique'],
        ['cle' => 'nomenclature',  'libelle' => 'Nomenclature budgétaire', 'icone' => 'list-nested',         'desc' => 'Référentiel complet Titre → Rubrique (PDF/Excel/CSV)'],
        ['cle' => 'bailleur',      'libelle' => 'Rapports Financiers',     'icone' => 'bank',                'desc' => 'Exécution par bailleur (CEEAC / PTF)'],
        ['cle' => 'categorie',     'libelle' => 'Rapports par catégorie',  'icone' => 'tags',                'desc' => 'Exécution par nature budgétaire'],
        ['cle' => 'rbm_gar',       'libelle' => 'Rapports RBM/GAR',        'icone' => 'diagram-3',           'desc' => 'Cadre technico-financier de rendement'],
        ['cle' => 'marches',       'libelle' => 'Marchés Publics / PPM',   'icone' => 'briefcase',           'desc' => 'Exécution du plan de passation des marchés'],
        ['cle' => 'contrats',      'libelle' => 'Registre des Contrats',   'icone' => 'file-earmark-text',   'desc' => 'Besoin → Engagement → Marché → Contrat → Paiement'],
        ['cle' => 'suivi_evaluation', 'libelle' => 'Suivi-évaluation', 'icone' => 'clipboard-data', 'desc' => 'Score S&E, évaluations, missions et plans', 'section' => 'pilotage'],
        ['cle' => 'trimestriel', 'libelle' => 'Rapport trimestriel', 'icone' => 'calendar3-range', 'desc' => 'Consolidation institutionnelle T1–T4 (stratégique, physique, financier)', 'section' => 'periodique'],
        ['cle' => 'semestriel', 'libelle' => 'Rapport semestriel', 'icone' => 'calendar2-range', 'desc' => 'Comparatif S1/S2 et tendances N/N-1', 'section' => 'periodique'],
        ['cle' => 'annuel', 'libelle' => 'Rapport annuel', 'icone' => 'journal-bookmark', 'desc' => 'Performance institutionnelle consolidée', 'section' => 'periodique'],
        ['cle' => 'performance_pilier', 'libelle' => 'Performance par pilier', 'icone' => 'layers', 'desc' => 'Consolidation financière et physique par pilier', 'section' => 'strategique'],
        ['cle' => 'performance_axe', 'libelle' => 'Performance par axe', 'icone' => 'diagram-2', 'desc' => 'Classement et exécution par axe stratégique', 'section' => 'strategique'],
        ['cle' => 'performance_departement', 'libelle' => 'Performance départements', 'icone' => 'building', 'desc' => 'Indice composite et budget par département', 'section' => 'strategique'],
        ['cle' => 'recettes_mobilisation', 'libelle' => 'Mobilisation contributions', 'icone' => 'globe2', 'desc' => 'Contributions des États membres (hub unifié)', 'section' => 'recettes'],
        ['cle' => 'recettes_financier', 'libelle' => 'Rapport financier recettes', 'icone' => 'cash-coin', 'desc' => 'Synthèse recettes, mobilisation et arriérés', 'section' => 'recettes'],
        ['cle' => 'tresorerie_flux', 'libelle' => 'Flux de trésorerie', 'icone' => 'arrow-left-right', 'desc' => 'Encaissements, décaissements et évolution mensuelle', 'section' => 'tresorerie'],
        ['cle' => 'tresorerie_dette_fournisseurs', 'libelle' => 'Dette fournisseurs', 'icone' => 'building', 'desc' => 'Restes à payer par bénéficiaire et par engagement', 'section' => 'tresorerie'],
        ['cle' => 'ipsas_etat_financier', 'libelle' => 'État financier IPSAS', 'icone' => 'journal-medical', 'desc' => 'Bilan, compte de résultat et annexes SYSCOHADA/IPSAS', 'section' => 'ipsas'],
        ['cle' => null, 'route' => 'reporting.techniques', 'libelle' => 'Rapports techniques', 'icone' => 'gear', 'desc' => 'Santé plateforme, intégrité data, journaux audit'],
        ['cle' => 'recommandations', 'libelle' => 'Recommandations',     'icone' => 'check2-square', 'desc' => 'Suivi des recommandations institutionnelles'],
        ['cle' => 'formations',    'libelle' => 'Formations',              'icone' => 'mortarboard',   'desc' => 'Ateliers, participation, coûts, genre, impact'],
        ['cle' => 'immobilisations', 'libelle' => 'Inventaires & immobilisations', 'icone' => 'box-seam', 'desc' => 'Registre patrimonial, amortissements et VNC'],
        ['cle' => null, 'route' => 'reporting.modeles.index', 'libelle' => 'Rapports personnalisés', 'icone' => 'sliders', 'desc' => 'Constructeur de rapports sur mesure'],
    ];
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Reporting & Analyse</h4>
        <span class="text-muted small">Système centralisé de reporting institutionnel · Exercice
            <span class="badge bg-primary">{{ $exercice->annee }}</span></span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reporting.cockpit', ['exercice' => $exercice->id]) }}" class="btn btn-primary btn-sm"><i class="bi bi-speedometer2 me-1"></i>Tableau de bord exécutif</a>
        @can('reporting.generate')
            <a href="{{ route('reporting.planification') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-check me-1"></i>Planification</a>
        @endcan
        <a href="{{ route('reporting.historique') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history me-1"></i>Centre d'exports</a>
    </div>
</div>

<form method="GET" class="mb-3">
    <div class="input-group input-group-sm" style="max-width:260px;">
        <span class="input-group-text">Exercice</span>
        <select name="exercice" class="form-select" onchange="this.form.submit()">
            @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($exercice->id === $ex->id)>{{ $ex->annee }}</option>@endforeach
        </select>
    </div>
</form>

<div class="row g-3">
    @php
        $sections = [
            'periodique' => 'Rapports institutionnels périodiques',
            'strategique' => 'Performance stratégique',
            'recettes' => 'Recettes & mobilisation',
            'tresorerie' => 'Trésorerie & dettes',
            'ipsas' => 'Conformité IPSAS',
            'pilotage' => 'Pilotage & suivi',
            null => 'Rapports opérationnels',
        ];
    @endphp
    @foreach ($sections as $sectionKey => $sectionTitre)
        @php $items = collect($catalogue)->filter(fn ($i) => ($i['section'] ?? null) === $sectionKey || ($sectionKey === null && ! isset($i['section']))); @endphp
        @if ($items->isNotEmpty())
            <div class="col-12"><h6 class="fw-bold text-primary mb-0">{{ $sectionTitre }}</h6></div>
            @foreach ($items as $item)
                <div class="col-md-4 col-xl-3">
                    @if (!empty($item['route']))
                        <a href="{{ route($item['route']) }}" class="text-decoration-none">
                            <div class="card border-0 shadow-sm h-100 report-card">
                                <div class="card-body">
                                    <i class="bi bi-{{ $item['icone'] }} fs-3 text-primary"></i>
                                    <div class="fw-bold mt-2 text-dark">{{ $item['libelle'] }}</div>
                                    <div class="text-muted small">{{ $item['desc'] }}</div>
                                    <span class="badge bg-success-subtle text-success mt-2">Disponible</span>
                                </div>
                            </div>
                        </a>
                    @elseif ($item['cle'])
                        <a href="{{ route('reporting.show', ['type' => $item['cle'], 'exercice' => $exercice->id]) }}" class="text-decoration-none">
                            <div class="card border-0 shadow-sm h-100 report-card">
                                <div class="card-body">
                                    <i class="bi bi-{{ $item['icone'] }} fs-3 text-primary"></i>
                                    <div class="fw-bold mt-2 text-dark">{{ $item['libelle'] }}</div>
                                    <div class="text-muted small">{{ $item['desc'] }}</div>
                                    <span class="badge bg-success-subtle text-success mt-2">Disponible</span>
                                </div>
                            </div>
                        </a>
                    @else
                        <div class="card border-0 shadow-sm h-100 opacity-75">
                            <div class="card-body">
                                <i class="bi bi-{{ $item['icone'] }} fs-3 text-secondary"></i>
                                <div class="fw-bold mt-2">{{ $item['libelle'] }}</div>
                                <div class="text-muted small">{{ $item['desc'] }}</div>
                                <span class="badge bg-secondary mt-2">Bientôt</span>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    @endforeach
</div>

@if ($recents->isNotEmpty())
    <h6 class="fw-bold mt-4">Derniers rapports générés</h6>
    <div class="card border-0 shadow-sm"><div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Référence</th><th>Type</th><th>Format</th><th>Version</th><th>Généré par</th><th>Le</th><th></th></tr></thead>
            <tbody>
            @foreach ($recents as $r)
                <tr>
                    <td class="font-monospace small">{{ $r->reference }}</td>
                    <td>{{ \App\Services\Reporting\RapportComposer::TYPES[$r->type] ?? $r->type }}</td>
                    <td><span class="badge bg-light text-dark text-uppercase">{{ $r->format }}</span></td>
                    <td>v{{ $r->version }}</td>
                    <td class="small">{{ $r->generePar?->name ?? '—' }}</td>
                    <td class="small">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                    <td><a href="{{ route('reporting.telecharger', $r) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-download"></i></a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
@endif
@endsection

@push('styles')<style>.report-card{transition:.15s}.report-card:hover{transform:translateY(-3px);box-shadow:0 .5rem 1rem rgba(10,74,143,.15)!important}</style>@endpush
