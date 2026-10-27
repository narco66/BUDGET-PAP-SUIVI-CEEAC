@extends('layouts.app')
@section('titre', 'Pilotage PAP — '.$exercice->annee)

@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $s = $pilotage['synthese'];
    $k = $pilotage['indicateurs'];
    $audit = $pilotage['audit'];
    $alertes = $pilotage['alertes'];
    $couleurs = ['brouillon'=>'secondary','en_validation'=>'warning','valide'=>'info','publie'=>'success','rejete'=>'danger','clos'=>'dark','en_revision'=>'primary'];
    $ton = fn ($t) => $t >= 75 ? 'success' : ($t >= 50 ? 'warning' : 'danger');
    $montantCeeac = $pilotage['piliers']->sum('montant_ceeac');
    $montantPtf = $pilotage['piliers']->sum('montant_ptf');
    $montantEngage = $pilotage['activites']->sum('montant_engage');
    $budgetTotal = max((float) $s['montant_prevu'], 1);
    $totalFeux = array_sum($feux);
    $niveauxAudit = ['critique' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $pilierActif = $pilierFiltre ? $pilotage['piliers']->firstWhere('code', $pilierFiltre) : null;
    $peutVoirActivite = auth()->user()->canAny(['tableau_bord.voir', 'reporting.view', 'rbm.voir']);
    $peutImporterPap = $pap
        ? auth()->user()->can('update', $pap)
        : auth()->user()->can('create', \App\Models\Pap::class);
@endphp

@push('styles')
<style>
    .pap-kpi .kpi-valeur { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
    .pap-kpi .kpi-libelle { font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); }
    .pap-kpi .progress { height: 5px; }
    .pap-ligne-pilier { cursor: pointer; }
    .pap-feux .progress { height: 10px; }
</style>
@endpush

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h1 class="h4 mb-1">Pilotage du Plan Annuel de Performance</h1>
        <p class="text-muted small mb-0">
            Exercice <strong>{{ $exercice->annee }}</strong>
            @if($pap)
                · <span class="badge bg-{{ $couleurs[$pap->statut] ?? 'secondary' }}">{{ str_replace('_', ' ', $pap->statut) }}</span>
                · v{{ $pap->version }}
            @else
                · <span class="badge bg-light text-dark border">Aucun PAP</span>
            @endif
        </p>
    </div>
    <div class="d-flex flex-wrap gap-1 align-items-center">
        <form method="GET" class="d-flex gap-1" id="formContextePap">
            <label for="filtreExercice" class="visually-hidden">Exercice</label>
            <select name="exercice" id="filtreExercice" class="form-select form-select-sm" data-auto-submit>
                @foreach($exercices as $ex)
                <option value="{{ $ex->annee }}" @selected($ex->id === $exercice->id)>{{ $ex->annee }}</option>
                @endforeach
            </select>
            <label for="filtrePilier" class="visually-hidden">Pilier</label>
            <select name="pilier" id="filtrePilier" class="form-select form-select-sm" data-auto-submit>
                <option value="">Tous les piliers</option>
                @foreach($pilotage['piliers'] as $pil)
                <option value="{{ $pil['code'] }}" @selected($pilierFiltre === $pil['code'])>{{ $pil['code'] }} — {{ Str::limit($pil['intitule'], 30) }}</option>
                @endforeach
            </select>
            <noscript><button class="btn btn-sm btn-outline-secondary">OK</button></noscript>
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
                        Le PAP actif sera archivé avant reconstruction de la chaîne RBM depuis le fichier Excel.
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label for="fichier_pap" class="form-label small fw-semibold">Fichier PAP Excel</label>
                        <input type="file" name="fichier_pap" id="fichier_pap" class="form-control @error('fichier_pap') is-invalid @enderror" accept=".xlsx,.xls" required>
                        @error('fichier_pap')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label for="modele_pap" class="form-label small fw-semibold">Modèle de contrôle</label>
                        <input type="file" name="modele_pap" id="modele_pap" class="form-control @error('modele_pap') is-invalid @enderror" accept=".xlsx,.xls">
                        @error('modele_pap')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <a href="{{ route('budget.modele.pap') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download me-1"></i>Télécharger le modèle
                    </a>
                    <span class="text-muted small">Colonnes attendues : pilier, axe, produit, sous-produit, activité, montants, ligne budget.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary" data-loading-text="Import en cours…">
                    <i class="bi bi-check2-circle me-1"></i>Lancer l'import
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@if($alertes['total_alertes'] > 0 || $audit['anomalies_critiques'] > 0)
<div class="alert alert-warning border-0 shadow-sm d-flex flex-wrap align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle fs-5"></i>
    <div class="flex-grow-1">
        @if($alertes['total_alertes'] > 0)
            <strong>{{ $alertes['total_alertes'] }} alerte(s) de performance</strong>
            ({{ $k['activites_en_retard'] }} en retard, {{ $k['activites_surconsommation'] }} en surconsommation)
        @endif
        @if($audit['anomalies_critiques'] > 0)
            @if($alertes['total_alertes'] > 0) · @endif
            <strong class="text-danger">{{ $audit['anomalies_critiques'] }} anomalie(s) critique(s)</strong> de données PAP
        @endif
    </div>
    @if($alertes['total_alertes'] > 0)
    <a href="#tab-alertes" class="btn btn-sm btn-warning" data-ouvrir-onglet="#tab-alertes">Voir le détail</a>
    @endif
</div>
@endif

{{-- Indicateurs clés --}}
<div class="row g-3 mb-3 pap-kpi">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="kpi-libelle">Activités</div>
            <div class="kpi-valeur text-primary">{{ $fmt($s['activites']) }}</div>
            <div class="small text-muted">
                <span class="text-warning-emphasis" title="En retard">{{ $k['activites_en_retard'] }} en retard</span>
                @if($k['activites_surconsommation'])· <span class="text-danger" title="Surconsommation">{{ $k['activites_surconsommation'] }} surconso.</span>@endif
            </div>
        </div></div>
    </div>
    @foreach([
        ['Réalisation physique', $s['taux_physique'], 'Moyenne déclarative des activités'],
        ['Exécution financière', $s['taux_financier'], 'Payé réel / budget prévu (pondéré)'],
        ['Indice global', $k['indice_global'], 'Moyenne physique + financier'],
    ] as [$libelle, $taux, $aide])
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3" title="{{ $aide }}">
            <div class="kpi-libelle">{{ $libelle }}</div>
            <div class="kpi-valeur text-{{ $ton($taux) }}">{{ $taux }}%</div>
            <div class="progress mt-2" role="progressbar" aria-label="{{ $libelle }}" aria-valuenow="{{ min($taux, 100) }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar bg-{{ $ton($taux) }}" style="width: {{ min($taux, 100) }}%"></div>
            </div>
        </div></div>
    </div>
    @endforeach
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="kpi-libelle">Budget prévu <small class="text-lowercase">(XAF)</small></div>
            <div class="kpi-valeur fs-5">{{ $fmt($s['montant_prevu']) }}</div>
            <div class="progress mt-2" title="CEEAC {{ $fmt($montantCeeac) }} · PTF {{ $fmt($montantPtf) }}">
                <div class="progress-bar bg-primary" style="width: {{ round($montantCeeac / $budgetTotal * 100, 1) }}%"></div>
                <div class="progress-bar bg-info" style="width: {{ round($montantPtf / $budgetTotal * 100, 1) }}%"></div>
            </div>
            <div class="small text-muted mt-1">
                <i class="bi bi-square-fill text-primary"></i> CEEAC {{ round($montantCeeac / $budgetTotal * 100) }}%
                · <i class="bi bi-square-fill text-info"></i> PTF {{ round($montantPtf / $budgetTotal * 100) }}%
            </div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="kpi-libelle">Payé <small class="text-lowercase">(XAF)</small></div>
            <div class="kpi-valeur fs-5 text-success">{{ $fmt($s['montant_paye']) }}</div>
            <div class="small text-muted mt-2">Engagé : <strong>{{ $fmt($montantEngage) }}</strong></div>
            <div class="small text-muted">Reste : {{ $fmt(max((float) $s['montant_prevu'] - (float) $s['montant_paye'], 0)) }}</div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <h2 class="h6 fw-bold mb-0"><i class="bi bi-layers me-1"></i>Performance par pilier</h2>
                <span class="small text-muted">Cliquez sur une barre pour explorer le pilier</span>
            </div>
            <div id="chartPilierPerf" style="min-height: 300px"></div>
        </div></div>
    </div>
    <div class="col-lg-4 d-flex flex-column gap-3">
        <div class="card border-0 shadow-sm"><div class="card-body pap-feux">
            <h2 class="h6 fw-bold mb-2"><i class="bi bi-stoplights me-1"></i>Feux des indicateurs</h2>
            @if($totalFeux > 0)
            <div class="progress mb-2" role="img" aria-label="{{ $feux['vert'] }} verts, {{ $feux['orange'] }} orange, {{ $feux['rouge'] }} rouges">
                <div class="progress-bar bg-success" style="width: {{ $feux['vert'] / $totalFeux * 100 }}%"></div>
                <div class="progress-bar bg-warning" style="width: {{ $feux['orange'] / $totalFeux * 100 }}%"></div>
                <div class="progress-bar bg-danger" style="width: {{ $feux['rouge'] / $totalFeux * 100 }}%"></div>
            </div>
            <div class="d-flex justify-content-between small">
                <span><i class="bi bi-circle-fill text-success"></i> {{ $feux['vert'] }} atteints</span>
                <span><i class="bi bi-circle-fill text-warning"></i> {{ $feux['orange'] }} à surveiller</span>
                <span><i class="bi bi-circle-fill text-danger"></i> {{ $feux['rouge'] }} critiques</span>
            </div>
            @else
            <p class="small text-muted mb-0">Aucune mesure d'indicateur enregistrée pour cet exercice.</p>
            @endif
        </div></div>

        <div class="card border-0 shadow-sm flex-grow-1"><div class="card-body">
            <h2 class="h6 fw-bold mb-2"><i class="bi bi-trophy me-1"></i>Top départements</h2>
            @forelse($pilotage['departements']->take(5) as $d)
            <div class="mb-2">
                <div class="d-flex justify-content-between small">
                    <span class="text-truncate me-2" title="{{ $d['libelle'] }}"><span class="text-muted">{{ $d['rang'] }}.</span> {{ $d['libelle'] }}</span>
                    <span class="fw-semibold text-nowrap">{{ $d['indice_performance'] }}%</span>
                </div>
                <div class="progress" style="height: 4px"><div class="progress-bar bg-{{ $ton($d['indice_performance']) }}" style="width: {{ min($d['indice_performance'], 100) }}%"></div></div>
            </div>
            @empty
            <p class="small text-muted mb-0">Aucune activité rattachée à un département.</p>
            @endforelse
        </div></div>
    </div>
</div>

{{-- Qualité des données PAP (audit d'intégrité) --}}
@if($audit['anomalies_total'] > 0)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold small"><i class="bi bi-clipboard-check me-1"></i>Qualité des données PAP :</span>
            @foreach($audit['anomalies'] as $anomalie)
            <span class="badge rounded-pill text-bg-{{ $niveauxAudit[$anomalie['niveau']] ?? 'secondary' }} fw-normal" title="{{ $anomalie['code'] }}">{{ $anomalie['message'] }}</span>
            @endforeach
        </div>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white">
        @if($pilierActif)
        <div class="d-flex flex-wrap align-items-center gap-2 py-1 mb-2">
            <span class="small text-muted">Filtre actif :</span>
            <span class="badge text-bg-primary fs-6 fw-normal">
                <code class="text-white">{{ $pilierActif['code'] }}</code> {{ Str::limit($pilierActif['intitule'], 60) }}
            </span>
            <a href="{{ route('paps.dashboard', ['exercice' => $exercice->annee]) }}" class="btn btn-sm btn-light"><i class="bi bi-x-lg me-1"></i>Retirer le filtre</a>
        </div>
        @endif
        <ul class="nav nav-tabs card-header-tabs" role="tablist" id="ongletsPap">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-piliers" type="button" role="tab">Piliers <span class="badge text-bg-light">{{ $pilotage['piliers']->count() }}</span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-produits" type="button" role="tab">Produits <span class="badge text-bg-light">{{ $produitsFiltres->count() }}</span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activites" type="button" role="tab">Activités <span class="badge text-bg-light">{{ $activitesFiltrees->count() }}</span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-alertes" type="button" role="tab">
                Alertes @if($alertes['total_alertes'] > 0)<span class="badge bg-danger">{{ $alertes['total_alertes'] }}</span>@endif
            </button></li>
        </ul>
    </div>
    <div class="card-body tab-content">
        <div class="tab-pane fade show active" id="tab-piliers" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light"><tr>
                        <th>Code</th><th>Pilier</th><th class="text-center">Act.</th>
                        <th class="text-end">Budget</th><th class="text-end">Part</th>
                        <th style="min-width:110px">Physique</th><th style="min-width:110px">Financier</th><th class="text-center">Indice</th>
                    </tr></thead>
                    <tbody>
                        @foreach($pilotage['piliers'] as $pil)
                        @php $urlPilier = route('paps.dashboard', ['exercice' => $exercice->annee, 'pilier' => $pil['code']]).'#tab-produits'; @endphp
                        <tr class="pap-ligne-pilier {{ $pilierFiltre === $pil['code'] ? 'table-primary' : '' }}" data-href="{{ $urlPilier }}">
                            <td><a href="{{ $urlPilier }}" class="text-decoration-none"><code>{{ $pil['code'] }}</code></a></td>
                            <td title="{{ $pil['intitule'] }}">{{ Str::limit($pil['intitule'], 50) }}</td>
                            <td class="text-center">{{ $pil['activites'] }}</td>
                            <td class="text-end small" title="CEEAC {{ $fmt($pil['montant_ceeac']) }} · PTF {{ $fmt($pil['montant_ptf']) }}">{{ $fmt($pil['budget_prevu']) }}</td>
                            <td class="text-end small text-muted">{{ round($pil['budget_prevu'] / $budgetTotal * 100, 1) }}%</td>
                            @foreach(['taux_physique', 'taux_financier'] as $cle)
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar bg-{{ $ton($pil[$cle]) }}" style="width:{{ min($pil[$cle], 100) }}%"></div></div>
                                    <span class="small text-muted">{{ $pil[$cle] }}%</span>
                                </div>
                            </td>
                            @endforeach
                            <td class="text-center"><span class="badge text-bg-{{ $ton($pil['indice_performance']) }}">{{ $pil['indice_performance'] }}%</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td colspan="2">Total PAP</td>
                            <td class="text-center">{{ $s['activites'] }}</td>
                            <td class="text-end small">{{ $fmt($s['montant_prevu']) }}</td>
                            <td class="text-end small">100%</td>
                            <td class="small">{{ $s['taux_physique'] }}%</td>
                            <td class="small">{{ $s['taux_financier'] }}%</td>
                            <td class="text-center">{{ $k['indice_global'] }}%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-produits" role="tabpanel">
            @include('paps.partials._performance_produits', ['produits' => $produitsFiltres])
        </div>
        <div class="tab-pane fade" id="tab-activites" role="tabpanel">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <div class="input-group input-group-sm" style="max-width: 320px">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="rechercheActivite" placeholder="Code ou intitulé…" aria-label="Rechercher une activité">
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Filtrer les activités">
                    <input type="radio" class="btn-check" name="filtreEtat" id="etatToutes" value="" checked>
                    <label class="btn btn-outline-secondary" for="etatToutes">Toutes</label>
                    <input type="radio" class="btn-check" name="filtreEtat" id="etatRetard" value="retard">
                    <label class="btn btn-outline-warning" for="etatRetard">En retard</label>
                    <input type="radio" class="btn-check" name="filtreEtat" id="etatSurconso" value="surconso">
                    <label class="btn btn-outline-danger" for="etatSurconso">Surconsommation</label>
                </div>
                <span class="small text-muted ms-auto" id="compteurActivites" aria-live="polite"></span>
            </div>
            @include('paps.partials._performance_activites', ['activites' => $activitesFiltrees, 'lienActivite' => $peutVoirActivite])
        </div>
        <div class="tab-pane fade" id="tab-alertes" role="tabpanel">
            @include('paps.partials._alertes', ['alertes' => $alertes, 'lienActivite' => $peutVoirActivite])
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/charts.js')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
// Après exécution des modules Vite (ApexCharts) : un module est différé, ce script ne l'est pas.
document.addEventListener('DOMContentLoaded', function () {
    // Changement d'exercice / de pilier.
    document.querySelectorAll('#formContextePap [data-auto-submit]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (sel.name === 'exercice') { document.getElementById('filtrePilier').value = ''; }
            sel.form.submit();
        });
    });

    // Onglet actif conservé dans l'URL (#tab-…), y compris après un drill-down.
    function ouvrirOnglet(cible) {
        const bouton = document.querySelector('#ongletsPap [data-bs-target="' + cible + '"]');
        if (bouton && window.bootstrap) { bootstrap.Tab.getOrCreateInstance(bouton).show(); }
    }
    if (location.hash.startsWith('#tab-')) { ouvrirOnglet(location.hash); }
    document.querySelectorAll('#ongletsPap [data-bs-toggle="tab"]').forEach(function (bouton) {
        bouton.addEventListener('shown.bs.tab', function () {
            history.replaceState(null, '', bouton.getAttribute('data-bs-target'));
        });
    });
    document.querySelectorAll('[data-ouvrir-onglet]').forEach(function (lien) {
        lien.addEventListener('click', function (e) {
            e.preventDefault();
            ouvrirOnglet(lien.getAttribute('data-ouvrir-onglet'));
            document.getElementById('ongletsPap').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // Ligne de pilier cliquable.
    document.querySelectorAll('.pap-ligne-pilier[data-href]').forEach(function (tr) {
        tr.addEventListener('click', function (e) {
            if (!e.target.closest('a')) { location.href = tr.dataset.href; }
        });
    });

    // Recherche et filtre des activités (côté client).
    const recherche = document.getElementById('rechercheActivite');
    const lignes = Array.from(document.querySelectorAll('#tab-activites tr[data-activite]'));
    const compteur = document.getElementById('compteurActivites');
    function filtrerActivites() {
        const terme = recherche.value.trim().toLowerCase();
        const etat = document.querySelector('input[name="filtreEtat"]:checked').value;
        let visibles = 0;
        lignes.forEach(function (tr) {
            const ok = (!terme || tr.dataset.texte.includes(terme)) && (!etat || tr.dataset.etat.split(' ').includes(etat));
            tr.hidden = !ok;
            if (ok) { visibles++; }
        });
        compteur.textContent = visibles + ' / ' + lignes.length + ' activité(s)';
    }
    if (recherche && lignes.length) {
        recherche.addEventListener('input', filtrerActivites);
        document.querySelectorAll('input[name="filtreEtat"]').forEach(function (r) { r.addEventListener('change', filtrerActivites); });
        filtrerActivites();
    }

    // Graphique : physique vs financier par pilier, clic = drill-down.
    const data = @json($chartPiliers);
    const pilierFiltre = @json($pilierFiltre);
    const el = document.querySelector('#chartPilierPerf');
    if (!el || !data.length || !window.ApexCharts) return;
    new ApexCharts(el, {
        chart: {
            type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit',
            events: {
                dataPointSelection: function (e, ctx, cfg) { location.href = data[cfg.dataPointIndex].url; },
                xAxisLabelClick: function (e, ctx, cfg) { if (data[cfg.labelIndex]) { location.href = data[cfg.labelIndex].url; } },
            },
        },
        plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } },
        series: [
            { name: 'Physique', data: data.map(p => p.physique) },
            { name: 'Financier', data: data.map(p => p.financier) },
        ],
        xaxis: { categories: data.map(p => p.x) },
        yaxis: { min: 0, max: Math.max(100, ...data.map(p => Math.max(p.physique, p.financier))), labels: { formatter: v => Math.round(v) + '%' } },
        annotations: pilierFiltre ? { xaxis: [{ x: pilierFiltre, borderColor: '#0d6efd', fillColor: '#0d6efd', opacity: .08 }] } : {},
        tooltip: {
            shared: true, intersect: false,
            x: { formatter: (v, opts) => data[opts.dataPointIndex] ? v + ' — ' + data[opts.dataPointIndex].label : v },
            y: { formatter: v => v + '%' },
        },
        colors: ['#0dcaf0', '#198754'],
        legend: { position: 'top' },
        dataLabels: { enabled: false },
        states: { active: { filter: { type: 'none' } } },
        noData: { text: 'Aucune donnée' },
    }).render();
});
</script>
@endpush
