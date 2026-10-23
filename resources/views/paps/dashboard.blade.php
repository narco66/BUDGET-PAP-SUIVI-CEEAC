@extends('layouts.app')
@section('titre', 'Pilotage PAP — '.$exercice->annee)

@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $s = $pilotage['synthese'];
    $k = $pilotage['indicateurs'];
    $couleurs = ['brouillon'=>'secondary','en_validation'=>'warning','valide'=>'info','publie'=>'success','rejete'=>'danger','clos'=>'dark','en_revision'=>'primary'];
    $produitsFiltres = $pilierFiltre
        ? $pilotage['produits']->filter(fn ($p) => str_starts_with($p['code'], 'PR-'.$pilierFiltre))
        : $pilotage['produits'];
    $activitesFiltres = $pilierFiltre
        ? $pilotage['activites']->filter(fn ($a) => str_starts_with($a['code'], 'ACT-'.$pilierFiltre))
        : $pilotage['activites'];
    $peutImporterPap = $pap
        ? auth()->user()->can('update', $pap)
        : auth()->user()->can('create', \App\Models\Pap::class);
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h4 mb-1">Pilotage du Plan Annuel de Performance</h1>
        <p class="text-muted small mb-0">
            Exercice <strong>{{ $exercice->annee }}</strong>
            @if($pap)
                · <span class="badge bg-{{ $couleurs[$pap->statut] ?? 'secondary' }}">{{ str_replace('_', ' ', $pap->statut) }}</span>
                · v{{ $pap->version }}
            @endif
        </p>
    </div>
    <div class="d-flex flex-wrap gap-1 align-items-center">
        <form method="GET" class="d-flex gap-1">
            <select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($exercices as $ex)
                <option value="{{ $ex->annee }}" @selected($ex->id === $exercice->id)>{{ $ex->annee }}</option>
                @endforeach
            </select>
        </form>
        @if($pap)
        <a href="{{ route('paps.show', $pap) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-text me-1"></i>Fiche PAP</a>
        @endif
        @if($peutImporterPap)
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalImportPap">
            <i class="bi bi-upload me-1"></i>Importer PAP
        </button>
        @endif
        <a href="{{ route('paps.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list me-1"></i>Liste PAP</a>
        @can('rapport.exporter')
        <a href="{{ route('reporting.generer', ['type' => 'pap', 'format' => 'pdf', 'exercice_id' => $exercice->id]) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-pdf me-1"></i>Rapport PDF</a>
        @endcan
    </div>
</div>

@if($peutImporterPap)
<div class="modal fade" id="modalImportPap" tabindex="-1" aria-labelledby="modalImportPapLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="{{ route('paps.dashboard.import') }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <input type="hidden" name="exercice_id" value="{{ $exercice->id }}">
            <div class="modal-header">
                <h2 class="modal-title h6" id="modalImportPapLabel"><i class="bi bi-upload me-1"></i>Importer le PAP {{ $exercice->annee }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex gap-2 py-2">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>
                        <strong>Remplacement complet.</strong>
                        Le PAP actif sera archive avant reconstruction de la chaine RBM depuis le fichier Excel.
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label for="fichier_pap" class="form-label small fw-semibold">Fichier PAP Excel</label>
                        <input type="file" name="fichier_pap" id="fichier_pap" class="form-control @error('fichier_pap') is-invalid @enderror" accept=".xlsx,.xls" required>
                        @error('fichier_pap')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label for="modele_pap" class="form-label small fw-semibold">Modele de controle</label>
                        <input type="file" name="modele_pap" id="modele_pap" class="form-control @error('modele_pap') is-invalid @enderror" accept=".xlsx,.xls">
                        @error('modele_pap')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <a href="{{ route('budget.modele.pap') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download me-1"></i>Telecharger le modele
                    </a>
                    <span class="text-muted small">Colonnes attendues : pilier, axe, produit, sous-produit, activite, montants, ligne budget.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle me-1"></i>Lancer l'import
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@if($pilotage['alertes']['total_alertes'] > 0)
<div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-3">
    <i class="bi bi-exclamation-triangle fs-5 me-2"></i>
    <div><strong>{{ $pilotage['alertes']['total_alertes'] }} alerte(s)</strong> — consultez l'onglet Alertes pour le détail.</div>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3 text-center">
            <div class="text-muted small">Activités</div>
            <div class="fs-4 fw-bold text-primary">{{ $s['activites'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3 text-center">
            <div class="text-muted small">Physique</div>
            <div class="fs-4 fw-bold text-info">{{ $s['taux_physique'] }}%</div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3 text-center">
            <div class="text-muted small">Financier réel</div>
            <div class="fs-4 fw-bold text-success">{{ $s['taux_financier'] }}%</div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3 text-center">
            <div class="text-muted small">Indice global</div>
            <div class="fs-4 fw-bold text-dark">{{ $k['indice_global'] }}%</div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3 text-center">
            <div class="text-muted small">Budget prévu</div>
            <div class="fs-6 fw-bold">{{ $fmt($s['montant_prevu']) }}</div>
            <div class="text-muted" style="font-size:.7rem">XAF</div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3 text-center">
            <div class="text-muted small">Montant payé</div>
            <div class="fs-6 fw-bold text-success">{{ $fmt($s['montant_paye']) }}</div>
            <div class="text-muted" style="font-size:.7rem">XAF</div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-layers me-1"></i>Performance par pilier</h2>
            <div id="chartPilierPerf"></div>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-trophy me-1"></i>Top départements</h2>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th>Département</th><th>Indice</th></tr></thead>
                    <tbody>
                        @forelse($pilotage['departements']->take(5) as $d)
                        <tr>
                            <td>{{ $d['rang'] }}</td>
                            <td>{{ Str::limit($d['libelle'], 22) }}</td>
                            <td class="fw-semibold">{{ $d['indice_performance'] }}%</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-muted">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-piliers" type="button">Piliers</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-produits" type="button">Produits</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activites" type="button">Activités</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-alertes" type="button">
                Alertes @if($pilotage['alertes']['total_alertes'] > 0)<span class="badge bg-danger ms-1">{{ $pilotage['alertes']['total_alertes'] }}</span>@endif
            </button></li>
        </ul>
    </div>
    <div class="card-body tab-content">
        <div class="tab-pane fade show active" id="tab-piliers">
            <p class="small text-muted">Cliquez sur un pilier pour filtrer produits et activités.</p>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr>
                        <th>Code</th><th>Pilier</th><th class="text-center">Act.</th>
                        <th class="text-end">CEEAC</th><th class="text-end">PTF</th>
                        <th>Physique</th><th>Financier</th><th class="text-center">Indice</th><th></th>
                    </tr></thead>
                    <tbody>
                        @foreach($pilotage['piliers'] as $pil)
                        <tr class="{{ $pilierFiltre === $pil['code'] ? 'table-primary' : '' }}">
                            <td><code>{{ $pil['code'] }}</code></td>
                            <td>{{ Str::limit($pil['intitule'], 40) }}</td>
                            <td class="text-center">{{ $pil['activites'] }}</td>
                            <td class="text-end small">{{ $fmt($pil['montant_ceeac']) }}</td>
                            <td class="text-end small">{{ $fmt($pil['montant_ptf']) }}</td>
                            <td>{{ $pil['taux_physique'] }}%</td>
                            <td>{{ $pil['taux_financier'] }}%</td>
                            <td class="text-center fw-semibold">{{ $pil['indice_performance'] }}%</td>
                            <td>
                                <a href="{{ route('paps.dashboard', ['exercice' => $exercice->annee, 'pilier' => $pil['code']]) }}" class="btn btn-xs btn-sm btn-outline-primary py-0">Drill-down</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($pilierFiltre)
            <div class="mt-2"><a href="{{ route('paps.dashboard', ['exercice' => $exercice->annee]) }}" class="btn btn-sm btn-light">Effacer filtre pilier {{ $pilierFiltre }}</a></div>
            @endif
        </div>
        <div class="tab-pane fade" id="tab-produits">
            @include('paps.partials._performance_produits', ['produits' => $produitsFiltres])
        </div>
        <div class="tab-pane fade" id="tab-activites">
            @include('paps.partials._performance_activites', ['activites' => $activitesFiltres])
        </div>
        <div class="tab-pane fade" id="tab-alertes">
            @include('paps.partials._alertes', ['alertes' => $pilotage['alertes']])
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/charts.js')
<script>
(function () {
    const data = @json($chartPiliers);
    const el = document.querySelector('#chartPilierPerf');
    if (!el || !data.length) return;
    new ApexCharts(el, {
        chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
        plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } },
        series: [
            { name: 'Physique %', data: data.map(p => p.physique) },
            { name: 'Financier %', data: data.map(p => p.financier) },
        ],
        xaxis: { categories: data.map(p => p.x) },
        yaxis: { max: 100, labels: { formatter: v => v + '%' } },
        colors: ['#0dcaf0', '#198754'],
        legend: { position: 'top' },
        dataLabels: { enabled: false },
        noData: { text: 'Aucune donnée' },
    }).render();
})();
</script>
@endpush
