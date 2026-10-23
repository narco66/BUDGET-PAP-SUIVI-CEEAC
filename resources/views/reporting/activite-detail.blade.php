@extends('layouts.app')
@section('titre', 'Activité '.$activite->code)

@php
    $fr = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $lb = $activite->ligneBudgetaire;
    $j = $svc->joursRetard($activite);
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <a href="{{ url()->previous() }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Retour au cockpit</a>
        <h4 class="fw-bold mb-0">{{ $activite->code }} — {{ $activite->intitule }}</h4>
        <span class="badge bg-secondary">{{ $activite->statut }}</span>
        @if ($j > 0)<span class="badge bg-danger">En retard de {{ $j }} j</span>@endif
    </div>
</div>

<div class="row g-3">
    {{-- Informations générales --}}
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-1"></i>Informations générales</h6>
        <dl class="row mb-0 small">
            <dt class="col-sm-4 text-muted">Code</dt><dd class="col-sm-8">{{ $activite->code }}</dd>
            <dt class="col-sm-4 text-muted">Intitulé</dt><dd class="col-sm-8">{{ $activite->intitule }}</dd>
            <dt class="col-sm-4 text-muted">Description</dt><dd class="col-sm-8">{{ $activite->description ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Département</dt><dd class="col-sm-8">{{ $activite->structure?->libelle ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Direction</dt><dd class="col-sm-8">{{ $activite->structure?->parent?->libelle ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Responsable</dt><dd class="col-sm-8">{{ $activite->responsable?->name ?? '—' }}</dd>
        </dl>
    </div></div></div>

    {{-- Référentiel RBM/GAR --}}
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-diagram-3 me-1"></i>Référentiel RBM / GAR</h6>
        <dl class="row mb-0 small">
            <dt class="col-sm-4 text-muted">Pilier</dt><dd class="col-sm-8">{{ $activite->produit?->axe?->pilier ? $activite->produit->axe->pilier->code.' — '.$activite->produit->axe->pilier->intitule : '—' }}</dd>
            <dt class="col-sm-4 text-muted">Axe</dt><dd class="col-sm-8">{{ $activite->produit?->axe ? $activite->produit->axe->code.' — '.$activite->produit->axe->intitule : '—' }}</dd>
            <dt class="col-sm-4 text-muted">Produit</dt><dd class="col-sm-8">{{ $activite->produit ? $activite->produit->code.' — '.$activite->produit->intitule : '—' }}</dd>
            <dt class="col-sm-4 text-muted">Sous-produit</dt><dd class="col-sm-8">{{ $activite->sousProduit ? $activite->sousProduit->code.' — '.$activite->sousProduit->intitule : '—' }}</dd>
        </dl>
        <h6 class="fw-bold mt-3 mb-2 small text-uppercase text-muted">Tâches associées ({{ $activite->taches->count() }})</h6>
        <ul class="list-group list-group-flush small">
            @forelse ($activite->taches as $t)
                <li class="list-group-item px-0 d-flex justify-content-between">
                    <span>{{ $t->code }} · {{ \Illuminate\Support\Str::limit($t->intitule, 40) }}</span>
                    <span class="text-muted">{{ $t->date_fin?->format('d/m/Y') ?? '—' }} · {{ (float) $t->taux_realisation }}%</span>
                </li>
            @empty<li class="list-group-item px-0 text-muted">Aucune tâche.</li>@endforelse
        </ul>
    </div></div></div>

    {{-- Performance --}}
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-graph-up me-1"></i>Performance</h6>
        <div class="d-flex gap-4 mb-3">
            <div><div class="text-muted small">Avancement physique</div><div class="fs-5 fw-bold text-primary">{{ (float) $activite->taux_realisation_physique }}%</div></div>
            <div><div class="text-muted small">Avancement financier</div><div class="fs-5 fw-bold text-success">{{ (float) $activite->taux_realisation_financier }}%</div></div>
        </div>
        <div class="table-responsive"><table class="table table-sm small mb-0">
            <thead class="table-light"><tr><th>Indicateur</th><th class="text-end">Cible</th><th class="text-end">Réalisé</th><th class="text-end">Taux</th><th>Feu</th></tr></thead>
            <tbody>
            @forelse ($activite->indicateurs as $ind)
                @php $m = $ind->mesures->sortByDesc('periode')->first(); @endphp
                <tr>
                    <td>{{ $ind->libelle }}</td>
                    <td class="text-end">{{ $ind->valeur_cible !== null ? $fr($ind->valeur_cible) : '—' }} {{ $ind->unite }}</td>
                    <td class="text-end">{{ $m ? $fr($m->valeur_realisee) : '—' }}</td>
                    <td class="text-end">{{ $m?->taux_atteinte !== null ? (float) $m->taux_atteinte.'%' : '—' }}</td>
                    <td>@if($m?->feu)<span class="badge bg-{{ $m->feu === 'vert' ? 'success' : ($m->feu === 'orange' ? 'warning text-dark' : 'danger') }}">{{ $m->feu }}</span>@else—@endif</td>
                </tr>
            @empty<tr><td colspan="5" class="text-muted">Aucun indicateur.</td></tr>@endforelse
            </tbody>
        </table></div>
    </div></div></div>

    {{-- Budgétaire --}}
    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-cash-stack me-1"></i>Informations budgétaires</h6>
        <dl class="row mb-0 small">
            <dt class="col-7 text-muted">Budget initial</dt><dd class="col-5 text-end">{{ $fr($lb->montant_ouvert ?? $activite->montant_total) }}</dd>
            <dt class="col-7 text-muted">Budget révisé</dt><dd class="col-5 text-end">{{ $lb && $lb->montant_revise !== null ? $fr($lb->montant_revise) : '—' }}</dd>
            <dt class="col-7 text-muted">Engagements</dt><dd class="col-5 text-end">{{ $fr($lb->montant_engage ?? 0) }}</dd>
            <dt class="col-7 text-muted">Liquidations</dt><dd class="col-5 text-end">{{ $fr($lb->montant_liquide ?? 0) }}</dd>
            <dt class="col-7 text-muted">Ordonnancements</dt><dd class="col-5 text-end">{{ $fr($lb->montant_ordonnance ?? 0) }}</dd>
            <dt class="col-7 text-muted">Paiements</dt><dd class="col-5 text-end text-success">{{ $fr($lb->montant_paye ?? 0) }}</dd>
            <dt class="col-7 text-muted">Solde disponible</dt><dd class="col-5 text-end fw-bold">{{ $lb ? $fr($lb->disponible) : '—' }}</dd>
            <dt class="col-7 text-muted">Source</dt><dd class="col-5 text-end">{{ $lb?->sourceFinancement?->libelle ?? '—' }}</dd>
        </dl>
    </div></div></div>

    {{-- Historique & pièces --}}
    <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-1"></i>Historique & pièces justificatives</h6>
        <div class="row">
            <div class="col-lg-7">
                <ul class="list-group list-group-flush small">
                    @forelse ($activite->journaux->sortByDesc('created_at')->take(15) as $log)
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span><span class="badge bg-light text-dark border">{{ $log->action }}</span> {{ $log->user?->name ?? 'Système' }}</span>
                            <span class="text-muted">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                        </li>
                    @empty<li class="list-group-item px-0 text-muted">Aucun mouvement journalisé.</li>@endforelse
                </ul>
            </div>
            <div class="col-lg-5">
                <div class="text-muted small text-uppercase mb-2">Documents (GED)</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($activite->gedDocuments as $doc)
                        <li class="list-group-item px-0"><i class="bi bi-file-earmark me-1"></i>{{ $doc->titre ?? $doc->nom ?? 'Document #'.$doc->id }}</li>
                    @empty<li class="list-group-item px-0 text-muted">Aucun document rattaché.</li>@endforelse
                </ul>
            </div>
        </div>
    </div></div></div>
</div>
@endsection
