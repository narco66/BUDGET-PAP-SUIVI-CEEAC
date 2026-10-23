@extends('layouts.app')
@section('titre', 'PAP '.$pap->exercice?->annee)

@php
$couleurs = ['brouillon'=>'secondary','en_validation'=>'warning','valide'=>'info','publie'=>'success','rejete'=>'danger','clos'=>'dark','en_revision'=>'primary'];
$etCouleurs = ['en_attente'=>'secondary','valide'=>'success','rejete'=>'danger','renvoye'=>'warning'];
@endphp

@section('contenu')
<div class="row g-3">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold">{{ $pap->libelle }}</h5>
                @if($pap->reference)<p class="small text-muted mb-0"><code>{{ $pap->reference }}</code></p>@endif
                <dl class="row mb-0 mt-3">
                    <dt class="col-5">Exercice</dt><dd class="col-7">{{ $pap->exercice?->annee }}</dd>
                    <dt class="col-5">Structure</dt><dd class="col-7">{{ $pap->structure?->libelle ?? '—' }}</dd>
                    <dt class="col-5">Responsable</dt><dd class="col-7">{{ $pap->responsable?->name ?? '—' }}</dd>
                    <dt class="col-5">Élaboré le</dt><dd class="col-7">{{ $pap->date_elaboration?->format('d/m/Y') ?? '—' }}</dd>
                    <dt class="col-5">Statut</dt><dd class="col-7"><span class="badge bg-{{ $couleurs[$pap->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$pap->statut) }}</span></dd>
                    <dt class="col-5">Version</dt><dd class="col-7">v{{ $pap->version }}</dd>
                    <dt class="col-5">Publié le</dt><dd class="col-7">{{ $pap->publie_le?->format('d/m/Y H:i') ?? '—' }}</dd>
                    @if($pap->validated_at)
                    <dt class="col-5">Validé le</dt><dd class="col-7">{{ $pap->validated_at->format('d/m/Y H:i') }} @if($pap->validateur)<span class="text-muted">· {{ $pap->validateur->name }}</span>@endif</dd>
                    @endif
                    @if($pap->motif_rejet)
                    <dt class="col-5">Motif rejet</dt><dd class="col-7 text-danger small">{{ $pap->motif_rejet }}</dd>
                    @endif
                    @if($pap->observations)
                    <dt class="col-5">Observations</dt><dd class="col-7 small">{{ $pap->observations }}</dd>
                    @endif
                </dl>

                <div class="d-flex flex-wrap gap-2 mt-2">
                    <a href="{{ route('paps.pdf', $pap) }}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer me-1"></i>Imprimer / PDF</a>
                    @can('update', $pap)
                    <a href="{{ route('paps.edit', $pap) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Modifier</a>
                    @endcan
                    @can('delete', $pap)
                    <form action="{{ route('paps.destroy', $pap) }}" method="POST" onsubmit="return confirm('Supprimer ce PAP ?')">@csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Supprimer</button>
                    </form>
                    @endcan
                </div>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="{{ route('paps.dashboard', ['pap' => $pap->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-speedometer2 me-1"></i>Pilotage</a>
                    <a href="{{ route('paps.execution-financiere', $pap) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-cash-stack me-1"></i>Exécution financière</a>
                    <a href="{{ route('rbm.arbre', $pap->exercice_id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-diagram-3 me-1"></i>Arbre RBM</a>
                    <a href="{{ route('suivi.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-data me-1"></i>Suivi</a>
                    <a href="{{ route('reporting.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bar-chart me-1"></i>Reporting</a>
                </div>

                @can('reviser', $pap)
                    <form action="{{ route('paps.revision', $pap) }}" method="POST" class="mt-3">@csrf
                        <label class="form-label small fw-semibold">Ouvrir une révision post-publication</label>
                        <textarea name="motif" class="form-control mb-2" rows="2" required placeholder="Motif de la révision (obligatoire)"></textarea>
                        <button class="btn btn-outline-primary w-100"><i class="bi bi-pencil-square me-1"></i>Ouvrir en révision</button>
                    </form>
                @endcan

                @can('soumettre', $pap)
                    <form action="{{ route('paps.soumettre', $pap) }}" method="POST" class="mt-3" onsubmit="return confirm('Soumettre le PAP au circuit de validation ?')">@csrf
                        <button class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>{{ $pap->statut === 'en_revision' ? 'Resoumettre après révision' : 'Soumettre au circuit de validation' }}</button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-speedometer2 me-1"></i>Performance (données réelles)</div>
            <div class="card-body">
                <div class="row text-center g-2">
                    <div class="col-4">
                        <div class="small text-muted">Activités</div>
                        <div class="fs-4 fw-bold">{{ $synthese['activites'] }}</div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Physique</div>
                        <div class="fs-4 fw-bold text-primary">{{ $synthese['taux_physique'] }} %</div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Financier réel</div>
                        <div class="fs-4 fw-bold text-success">{{ $synthese['taux_financier'] }} %</div>
                    </div>
                </div>
                <div class="small text-muted mt-2">Financier = montants payés sur lignes budgétaires / budget prévu activités.</div>
            </div>
        </div>

        @if($audit['anomalies_total'] > 0)
        <div class="card border-0 shadow-sm mt-3 border-start border-4 border-warning">
            <div class="card-header bg-white fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Audit ({{ $audit['anomalies_total'] }})</div>
            <ul class="list-group list-group-flush small">
                @foreach($audit['anomalies'] as $a)
                    <li class="list-group-item">{{ $a['message'] }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if($peutAgir)
        @include('partials._workflow_agir', ['action' => route('paps.agir', $pap)])
        @endif
    </div>

    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-2 me-1"></i>Circuit de validation
                @if($instance)<span class="badge bg-{{ ['en_cours'=>'warning','valide'=>'success','rejete'=>'danger'][$instance->statut] }} float-end">{{ str_replace('_',' ',$instance->statut) }}</span>@endif
            </div>
            <div class="card-body">
                @if($instance)
                    <ul class="list-unstyled mb-0">
                        @foreach($instance->etapes as $e)
                            <li class="d-flex align-items-start mb-3">
                                <span class="badge rounded-pill bg-{{ $etCouleurs[$e->statut] ?? 'secondary' }} me-3" style="width:2rem;">{{ $e->ordre }}</span>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ str_replace('_',' ',$e->role) }}
                                        @if($e->ordre === $instance->etape_courante && $instance->statut==='en_cours')<span class="badge bg-warning text-dark">en cours</span>@endif
                                    </div>
                                    <div class="small text-muted">
                                        {{ ucfirst($e->statut) }}
                                        @if($e->acteur) · {{ $e->acteur->name }} · {{ $e->decision_le?->format('d/m/Y H:i') }}@endif
                                    </div>
                                    @if($e->observation)<div class="small fst-italic text-secondary">« {{ $e->observation }} »</div>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">Le PAP n'a pas encore été soumis à un circuit de validation.</p>
                @endif
            </div>
        </div>

        @if($classementDepartements->isNotEmpty())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-trophy me-1"></i>Classement des départements</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>#</th><th>Département</th><th>Indice</th><th>Phys.</th><th>Fin.</th></tr></thead>
                    <tbody>
                        @foreach($classementDepartements as $d)
                        <tr>
                            <td>{{ $d['rang'] }}</td>
                            <td>{{ $d['libelle'] }}</td>
                            <td class="fw-semibold">{{ $d['indice_performance'] }} %</td>
                            <td>{{ $d['taux_physique'] }} %</td>
                            <td>{{ $d['taux_financier'] }} %</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($consolidation->isNotEmpty())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-building me-1"></i>Consolidation par structure</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Structure</th><th>Act.</th><th>Phys.</th><th>Fin.</th></tr></thead>
                    <tbody>
                        @foreach($consolidation as $s)
                        <tr>
                            <td>{{ $s['libelle'] }}</td>
                            <td>{{ $s['activites'] }}</td>
                            <td>{{ $s['taux_physique'] }} %</td>
                            <td>{{ $s['taux_financier'] }} %</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($pap->historiques->isNotEmpty())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-clock-history me-1"></i>Historique PAP</div>
            <ul class="list-group list-group-flush small">
                @foreach($pap->historiques as $h)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>
                        <strong>v{{ $h->version }}</strong> — {{ $h->action }}
                        @if($h->auteur) · {{ $h->auteur->name }}@endif
                        · {{ $h->created_at->format('d/m/Y H:i') }}
                        @if($h->snapshot_complet)<span class="badge bg-success ms-1">snapshot complet</span>@endif
                    </span>
                    @if($h->snapshot_complet)
                    <a href="{{ route('paps.snapshot', [$pap, $h]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-camera me-1"></i>Consulter</a>
                    @endif
                </li>
                @endforeach
            </ul>
        </div>
        @endif
        @include('partials._ged_documents', ['attachable' => $pap, 'type' => 'pap'])
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pap-tab-produits" type="button"><i class="bi bi-box me-1"></i>Produits ({{ $performanceProduits->count() }})</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pap-tab-activites" type="button"><i class="bi bi-list-task me-1"></i>Activités ({{ $performanceActivites->count() }})</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pap-tab-alertes" type="button">
                <i class="bi bi-bell me-1"></i>Alertes @if($alertes['total_alertes'] > 0)<span class="badge bg-danger">{{ $alertes['total_alertes'] }}</span>@endif
            </button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pap-tab-risques" type="button">
                <i class="bi bi-shield-exclamation me-1"></i>Risques ({{ $risques->count() }})
            </button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pap-tab-ecarts" type="button">
                <i class="bi bi-graph-down-arrow me-1"></i>Écarts ({{ $activitesEcarts->count() }})
            </button></li>
        </ul>
    </div>
    <div class="card-body tab-content">
        <div class="tab-pane fade show active" id="pap-tab-produits">
            @include('paps.partials._performance_produits', ['produits' => $performanceProduits])
        </div>
        <div class="tab-pane fade" id="pap-tab-activites">
            @include('paps.partials._performance_activites', ['activites' => $performanceActivites])
        </div>
        <div class="tab-pane fade" id="pap-tab-alertes">
            @include('paps.partials._alertes', ['alertes' => $alertes])
        </div>
        <div class="tab-pane fade" id="pap-tab-risques">
            @include('paps.partials._risques_gar', compact('pap', 'risques', 'mesures', 'activitesSelect', 'responsables'))
        </div>
        <div class="tab-pane fade" id="pap-tab-ecarts">
            @include('paps.partials._ecarts_activites', compact('pap', 'activitesEcarts'))
        </div>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <a href="{{ route('paps.dashboard', ['pap' => $pap->id]) }}" class="btn btn-outline-primary"><i class="bi bi-speedometer2 me-1"></i>Tableau de pilotage complet</a>
    <a href="{{ route('paps.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
@endsection
