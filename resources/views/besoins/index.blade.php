@extends('layouts.app')
@section('titre', 'Expressions du besoin')
@php
$c = ['brouillon'=>'secondary','soumis'=>'warning','valide'=>'success','rejete'=>'danger','converti'=>'info'];
$prioriteC = ['haute'=>'danger','moyenne'=>'warning','basse'=>'secondary'];
@endphp
@section('contenu')
<div class="d-flex justify-content-end gap-2 mb-3 flex-wrap">
    @can('besoin.valider')
        <a href="{{ route('besoins.consolidation') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-collection me-1"></i>Consolidation</a>
    @endcan
    @can('depenses.export')
        <div class="btn-group btn-group-sm">
            <a href="{{ route('depense.export.besoins') }}" class="btn btn-outline-success">Export besoins</a>
            <a href="{{ route('depense.export.engagements') }}" class="btn btn-outline-success">Journal engagements</a>
            <a href="{{ route('depense.export.liquidations') }}" class="btn btn-outline-success">Journal liquidations</a>
            <a href="{{ route('depense.export.ordonnancements') }}" class="btn btn-outline-success">Journal ordonnancements</a>
            <a href="{{ route('depense.export.consolide') }}" class="btn btn-outline-success">Journal consolidé</a>
            <a href="{{ route('depense.export.ecritures') }}" class="btn btn-outline-success">Écritures comptables</a>
            <a href="{{ route('depense.export.paiements') }}" class="btn btn-outline-success">Journal paiements</a>
            <a href="{{ route('depense.export.restes') }}" class="btn btn-outline-success">Restes à payer</a>
        </div>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Archiver une version officielle traçable (versioning, hash, QR, GED)">
                <i class="bi bi-shield-check me-1"></i>Archiver (officiel)
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">Journal officiel traçable</h6></li>
                @foreach ([
                    'besoins' => 'Journal des besoins',
                    'engagements' => 'Journal des engagements',
                    'liquidations' => 'Journal des liquidations',
                    'ordonnancements' => 'Journal des ordonnancements',
                    'paiements' => 'Journal des paiements',
                    'consolide' => 'Journal consolidé',
                    'ecritures' => 'Écritures comptables',
                    'restes-a-payer' => 'Restes à payer',
                    'restes-a-engager' => 'Restes à engager',
                ] as $cle => $libelle)
                    <li><a class="dropdown-item" href="{{ route('depense.export.archiver', $cle) }}"><i class="bi bi-file-earmark-check me-2"></i>{{ $libelle }}</a></li>
                @endforeach
            </ul>
        </div>
    @endcan
    @can('paiement.valider')
        <a href="{{ route('depense.rapprochement.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bank me-1"></i>Rapprochement bancaire</a>
    @endcan
    @can('besoin.creer')<a href="{{ route('besoins.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau besoin</a>@endcan
</div>

<form method="GET" class="card border-0 shadow-sm mb-3"><div class="card-body py-2">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-0">Tâche PAP</label>
            <select name="tache_id" class="form-select form-select-sm">
                <option value="">Toutes</option>
                @foreach ($taches as $t)
                    <option value="{{ $t->id }}" @selected(request('tache_id')==$t->id)>{{ $t->code }} · {{ \Illuminate\Support\Str::limit($t->intitule,30) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-0">Priorité</label>
            <select name="priorite" class="form-select form-select-sm">
                <option value="">Toutes</option>
                @foreach ($priorites as $k => $v)
                    <option value="{{ $k }}" @selected(request('priorite')==$k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filtrer</button></div>
    </div>
</div></form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>N°</th><th>Objet</th><th>Tâche</th><th>Priorité</th><th class="text-end">Montant estimé</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse ($besoins as $b)
            <tr>
                <td><code>{{ $b->numero }}</code></td>
                <td>{{ \Illuminate\Support\Str::limit($b->objet, 50) }}</td>
                <td class="small text-muted">{{ $b->tache?->code ?? '—' }}</td>
                <td><span class="badge bg-{{ $prioriteC[$b->priorite] ?? 'secondary' }}">{{ $priorites[$b->priorite] ?? $b->priorite }}</span></td>
                <td class="text-end">{{ number_format($b->montant_estime,0,',',' ') }}</td>
                <td><span class="badge bg-{{ $c[$b->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$b->statut) }}</span></td>
                <td class="text-end"><a href="{{ route('besoins.show', $b) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-4">Aucune expression du besoin.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $besoins->links() }}</div>
@endsection
