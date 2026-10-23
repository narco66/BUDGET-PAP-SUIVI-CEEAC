@extends('layouts.app')
@section('titre', 'Campagne · '.$campagne->libelle)
@php
$fmt = fn ($n) => number_format((float)$n, 0, ',', ' ');
$sc = ['saisie'=>'secondary','verifiee'=>'info','validee'=>'success'];
@endphp
@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="badge bg-{{ $campagne->statut==='ouverte'?'success':'secondary' }}">{{ $campagne->statut }}</span>
        <strong>{{ $campagne->libelle }}</strong> · <code>{{ $campagne->periode }}</code> · Exercice {{ $campagne->exercice?->annee }}
    </div>
    <div class="d-flex gap-1">
        @can('suivi.exporter')
        <div class="btn-group btn-group-sm">
            <a href="{{ route('suivi.export.collectes', [$campagne, 'xlsx']) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i></a>
            <a href="{{ route('suivi.export.collectes', [$campagne, 'csv']) }}" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv"></i></a>
            <a href="{{ route('suivi.export.collectes', [$campagne, 'pdf']) }}" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i></a>
        </div>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Archiver une version officielle traçable (versioning, hash, QR, GED)">
                <i class="bi bi-shield-check"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Archiver (officiel)</h6></li>
                @foreach (['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF'] as $fmt => $libelle)
                    <li><a class="dropdown-item" href="{{ route('suivi.export.collectes.archiver', [$campagne, $fmt]) }}"><i class="bi bi-file-earmark-check me-2"></i>Collectes {{ $libelle }}</a></li>
                @endforeach
            </ul>
        </div>
        @endcan
        @can('suivi.valider')
        @if($campagne->estOuverte())
        <form action="{{ route('suivi.cloturer', $campagne) }}" method="POST" onsubmit="return confirm('Clôturer la campagne ?')">@csrf<button class="btn btn-sm btn-outline-dark"><i class="bi bi-lock me-1"></i>Clôturer</button></form>
        @endif
    @endcan
    </div>
</div>

@if($realisationsSousProduits->isNotEmpty())
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h6 class="fw-bold"><i class="bi bi-diagram-2 me-1"></i>Consolidation sous-produits (snapshot)</h6>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Sous-produit</th><th class="text-end">Activités</th><th class="text-end">Collectes validées</th><th class="text-end">Taux phys.</th><th class="text-end">Taux fin.</th></tr></thead>
        <tbody>
        @foreach($realisationsSousProduits as $rsp)
            <tr>
                <td><code>{{ $rsp->sousProduit?->code }}</code> {{ \Illuminate\Support\Str::limit($rsp->sousProduit?->intitule, 40) }}</td>
                <td class="text-end">{{ $rsp->nb_activites }}</td>
                <td class="text-end">{{ $rsp->nb_collectes_validees }}</td>
                <td class="text-end">{{ $rsp->taux_physique }}%</td>
                <td class="text-end">{{ $rsp->taux_financier }}%</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div></div>
@endif

@if($realisationsProduits->isNotEmpty())
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h6 class="fw-bold"><i class="bi bi-box-seam me-1"></i>Consolidation produits (snapshot)</h6>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Produit</th><th class="text-end">Activités</th><th class="text-end">Collectes validées</th><th class="text-end">Taux phys.</th><th class="text-end">Taux fin.</th></tr></thead>
        <tbody>
        @foreach($realisationsProduits as $rp)
            <tr>
                <td><code>{{ $rp->produit?->code }}</code> {{ \Illuminate\Support\Str::limit($rp->produit?->intitule, 40) }}</td>
                <td class="text-end">{{ $rp->nb_activites }}</td>
                <td class="text-end">{{ $rp->nb_collectes_validees }}</td>
                <td class="text-end">{{ $rp->taux_physique }}%</td>
                <td class="text-end">{{ $rp->taux_financier }}%</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div></div>
@endif

@if($alertes->isNotEmpty())
<div class="card border-0 shadow-sm mb-3 border-start border-warning border-3"><div class="card-body">
    <h6 class="fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Alertes performance ({{ $alertes->count() }})</h6>
    <ul class="list-group list-group-flush small mb-0">
        @foreach($alertes as $alerte)
            <li class="list-group-item px-0 d-flex justify-content-between align-items-start">
                <span>
                    <span class="badge bg-{{ match($alerte->gravite){ 'elevee'=>'danger','moyenne'=>'warning text-dark',default=>'secondary' } }} me-1">{{ $alerte->gravite }}</span>
                    {{ $alerte->message }}
                </span>
                <code class="text-muted ms-2">{{ $alerte->reference }}</code>
            </li>
        @endforeach
    </ul>
</div></div>
@endif

@if($consolidation->isNotEmpty())
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h6 class="fw-bold"><i class="bi bi-bar-chart me-1"></i>Consolidation des taux validés par structure</h6>
    <div class="row">
    @foreach($consolidation as $cons)
        <div class="col-md-3 mb-2"><div class="border rounded p-2 small">
            <div class="fw-semibold">{{ \Illuminate\Support\Str::limit($cons->libelle,24) }}</div>
            <div>Phys. <strong>{{ $cons->phys }}%</strong> · Fin. <strong>{{ $cons->fin }}%</strong> <span class="text-muted">({{ $cons->n }})</span></div>
        </div></div>
    @endforeach
    </div>
</div></div>
@endif

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Activité</th><th>Structure</th><th class="text-end">Budget</th><th style="width:90px">Taux %</th><th style="width:80px">Qté prév.</th><th style="width:80px">Qté réal.</th><th style="width:120px">Réalisé (XAF)</th><th>Écart</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @foreach($activites as $a)
            @php $col = $a->collectes->first(); @endphp
            <tr>
                <td><code>{{ $a->code }}</code> {{ \Illuminate\Support\Str::limit($a->intitule,32) }}</td>
                <td class="small text-muted">{{ $a->structure?->sigle ?? '—' }}</td>
                <td class="text-end small">{{ $fmt($a->montant_total) }}</td>
                @if($campagne->estOuverte() && (!$col || $col->statut!=='validee'))
                    <form action="{{ route('suivi.collecte.enregistrer', [$campagne, $a]) }}" method="POST">@csrf
                        <td><input type="number" step="0.01" min="0" max="100" name="taux_physique" value="{{ $col->taux_physique ?? '' }}" class="form-control form-control-sm" @cannot('suivi.creer') disabled @endcannot></td>
                        <td><input type="number" step="0.0001" min="0" name="quantite_prevue" value="{{ $col->quantite_prevue ?? '' }}" class="form-control form-control-sm" @cannot('suivi.creer') disabled @endcannot></td>
                        <td><input type="number" step="0.0001" min="0" name="quantite_realisee" value="{{ $col->quantite_realisee ?? '' }}" class="form-control form-control-sm" @cannot('suivi.creer') disabled @endcannot></td>
                        <td><input type="number" step="0.01" min="0" name="montant_realise" value="{{ $col->montant_realise ?? '' }}" class="form-control form-control-sm" @cannot('suivi.creer') disabled @endcannot></td>
                        <td class="small">{{ $col ? $col->ecart.' pts' : '—' }}</td>
                        <td>@if($col)<span class="badge bg-{{ $sc[$col->statut] }}">{{ $col->statut }}</span>@else<span class="badge bg-light text-dark">—</span>@endif</td>
                        <td class="text-end text-nowrap">
                            @can('suivi.creer')<button class="btn btn-sm btn-outline-primary" title="Enregistrer"><i class="bi bi-save"></i></button>@endcan
                        </td>
                    </form>
                @else
                    <td class="text-center">{{ $col?->taux_physique ?? '—' }}</td>
                    <td class="text-center">{{ $col?->quantite_prevue ?? '—' }}</td>
                    <td class="text-center">{{ $col?->quantite_realisee ?? '—' }}</td>
                    <td class="text-end small">{{ $col ? $fmt($col->montant_realise) : '—' }}</td>
                    <td class="small">{{ $col ? $col->ecart.' pts' : '—' }}</td>
                    <td>@if($col)<span class="badge bg-{{ $sc[$col->statut] }}">{{ $col->statut }}</span>@endif</td>
                    <td></td>
                @endif
            </tr>
            @if($col && $col->id)
            <tr class="table-light"><td colspan="10" class="py-2">
                @include('suivi.partials._collecte_justificatifs', ['collecte' => $col->load('pieces')])
            </td></tr>
            @endif
            @if($col && $col->statut !== 'validee')
            <tr class="table-light"><td colspan="10" class="py-1">
                @can('suivi.valider')
                <div class="d-flex gap-1 justify-content-end">
                    @if($col->statut==='saisie')<form action="{{ route('suivi.collecte.verifier', $col) }}" method="POST">@csrf<button class="btn btn-sm btn-outline-info"><i class="bi bi-check me-1"></i>Vérifier</button></form>@endif
                    @if($col->statut==='verifiee')<form action="{{ route('suivi.collecte.valider', $col) }}" method="POST">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check2-all me-1"></i>Valider</button></form>@endif
                    <form action="{{ route('suivi.collecte.rejeter', $col) }}" method="POST">@csrf<button class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-counterclockwise"></i></button></form>
                </div>
                @endcan
            </td></tr>
            @endif
        @endforeach
        </tbody>
    </table>
</div></div>
<div class="mt-2">{{ $activites->links() }}</div>
@include('partials._ged_documents', ['attachable' => $campagne, 'type' => 'suivi'])
<div class="mt-2"><a href="{{ route('suivi.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>
@endsection
