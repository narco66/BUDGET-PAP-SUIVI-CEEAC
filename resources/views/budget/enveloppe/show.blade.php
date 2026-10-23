@extends('layouts.app')
@section('titre', $budget->reference)

@php
$couleurs = ['brouillon'=>'secondary','en_validation'=>'warning','valide'=>'info','adopte'=>'success','rejete'=>'danger','clos'=>'dark','en_revision'=>'primary'];
$etCouleurs = ['en_attente'=>'secondary','valide'=>'success','rejete'=>'danger','renvoye'=>'warning'];
@endphp

@section('contenu')
<div class="row g-3">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold">{{ $budget->libelle }}</h5>
                <p class="small text-muted mb-0"><code>{{ $budget->reference }}</code></p>
                <dl class="row mb-0 mt-3">
                    <dt class="col-5">Exercice</dt><dd class="col-7">{{ $budget->exercice?->annee }}</dd>
                    <dt class="col-5">Montant total</dt><dd class="col-7 fw-semibold">{{ number_format($montantTotal, 0, ',', ' ') }} FCFA</dd>
                    <dt class="col-5">Statut</dt><dd class="col-7"><span class="badge bg-{{ $couleurs[$budget->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$budget->statut) }}</span></dd>
                    <dt class="col-5">Version</dt><dd class="col-7">v{{ $budget->version }}</dd>
                    <dt class="col-5">Adopté le</dt><dd class="col-7">{{ $budget->adopte_le?->format('d/m/Y H:i') ?? '—' }}</dd>
                    @if($budget->motif_rejet)
                    <dt class="col-5">Motif rejet</dt><dd class="col-7 text-danger small">{{ $budget->motif_rejet }}</dd>
                    @endif
                </dl>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    @can('update', $budget)<a href="{{ route('budget.enveloppe.edit', $budget) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
                    <a href="{{ route('budget.index', ['exercice' => $budget->exercice_id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-ul me-1"></i>Lignes budgétaires</a>
                    @can('preparer', $budget)
                    <a href="{{ route('budget.enveloppe.preparations', $budget) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-clipboard-check me-1"></i>Préparation & arbitrage</a>
                    @endcan
                    @can('simuler', $budget)
                    <a href="{{ route('budget.enveloppe.simulations', $budget) }}" class="btn btn-sm btn-outline-info"><i class="bi bi-sliders me-1"></i>Simulations</a>
                    @endcan
                    @can('rectifier', $budget)
                    <a href="{{ route('budget.enveloppe.revisions', $budget) }}" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-left-right me-1"></i>Révisions</a>
                    @endcan
                    @can('view', $budget)
                    <a href="{{ route('budget.enveloppe.rapport', $budget) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-bar-graph me-1"></i>Rapport</a>
                    @endcan
                </div>

                @can('reviser', $budget)
                <form action="{{ route('budget.enveloppe.revision', $budget) }}" method="POST" class="mt-3">@csrf
                    <label class="form-label small fw-semibold">Ouvrir une révision post-adoption</label>
                    <textarea name="motif" class="form-control mb-2" rows="2" required placeholder="Motif obligatoire"></textarea>
                    <button class="btn btn-outline-primary w-100"><i class="bi bi-pencil-square me-1"></i>Ouvrir en révision</button>
                </form>
                @endcan

                @can('soumettre', $budget)
                <form action="{{ route('budget.enveloppe.soumettre', $budget) }}" method="POST" class="mt-3" onsubmit="return confirm('Soumettre au circuit de validation ?')">@csrf
                    <button class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>{{ $budget->statut === 'en_revision' ? 'Resoumettre' : 'Soumettre au circuit' }}</button>
                </form>
                @endcan
            </div>
        </div>

        @if($audit['anomalies_total'] > 0)
        <div class="card border-0 shadow-sm mt-3 border-start border-4 border-warning">
            <div class="card-header bg-white fw-bold">Audit — {{ $audit['anomalies_total'] }} anomalie(s)</div>
            <div class="card-body small text-muted">Exécutez <code>budget:audit --exercice={{ $budget->exercice?->annee }}</code> avant soumission.</div>
        </div>
        @endif

        @if($peutAgir)
        @include('partials._workflow_agir', ['action' => route('budget.enveloppe.agir', $budget)])
        @endif
    </div>

    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-2 me-1"></i>Circuit de validation</div>
            <div class="card-body">
                @if($instance)
                <ul class="list-unstyled mb-0">
                    @foreach($instance->etapes as $e)
                    <li class="d-flex align-items-start mb-3">
                        <span class="badge rounded-pill bg-{{ $etCouleurs[$e->statut] ?? 'secondary' }} me-3" style="width:2rem;">{{ $e->ordre }}</span>
                        <div>
                            <div class="fw-semibold">{{ str_replace('_',' ',$e->role) }}</div>
                            <div class="small text-muted">{{ ucfirst($e->statut) }} @if($e->acteur)· {{ $e->acteur->name }}@endif</div>
                        </div>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-muted mb-0">Budget non encore soumis au circuit.</p>
                @endif
            </div>
        </div>

        @if($budget->historiques->isNotEmpty())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-clock-history me-1"></i>Historique</div>
            <ul class="list-group list-group-flush small">
                @foreach($budget->historiques as $h)
                <li class="list-group-item">
                    <strong>v{{ $h->version }}</strong> — {{ $h->action }}
                    @if($h->auteur)· {{ $h->auteur->name }}@endif
                    · {{ $h->created_at->format('d/m/Y H:i') }}
                    @if($h->snapshot_complet)<span class="badge bg-success ms-1">snapshot complet</span>@endif
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('budget.enveloppe.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
@endsection
