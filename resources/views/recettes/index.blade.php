@extends('layouts.app')
@section('titre', 'Gestion des recettes')

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $tauxReal = $synthese['montant_prevu'] > 0 ? round($synthese['montant_realise'] / $synthese['montant_prevu'] * 100, 1) : 0;
@endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2 flex-wrap" method="GET">
        <select name="exercice" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
            @foreach ($exercices as $ex)
                <option value="{{ $ex->id }}" @selected($exercice && $exercice->id === $ex->id)>Exercice {{ $ex->annee }}</option>
            @endforeach
        </select>
        <select name="categorie" class="form-select form-select-sm" style="width:200px;" onchange="this.form.submit()">
            <option value="">Toutes catégories</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}" @selected(request('categorie')==$cat->id)>{{ $cat->libelle }}</option>
            @endforeach
        </select>
        <select name="statut" class="form-select form-select-sm" style="width:170px;" onchange="this.form.submit()">
            <option value="">Tous statuts</option>
            @foreach (\App\Models\Recette::STATUTS as $k => $v)
                <option value="{{ $k }}" @selected(request('statut')===$k)>{{ $v }}</option>
            @endforeach
        </select>
    </form>
    <div class="d-flex gap-2">
        @can('recette.voir')
            <a href="{{ route('previsions-recettes.index') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-graph-up-arrow me-1"></i>Prévisions</a>
            <a href="{{ route('recettes.rapports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-bar-graph me-1"></i>Rapports</a>
        @endcan
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Recettes prévues</div>
            <div class="h4 mb-0">{{ $fmt($synthese['montant_prevu']) }} <small class="text-muted">XAF</small></div>
            <div class="small text-muted">{{ $synthese['previsions'] }} prévision(s)</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Recettes réalisées</div>
            <div class="h4 mb-0 text-success">{{ $fmt($synthese['montant_realise']) }} <small class="text-muted">XAF</small></div>
            <div class="small text-muted">{{ $synthese['recettes'] }} recette(s)</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Taux de réalisation</div>
            <div class="h4 mb-0">{{ $tauxReal }} %</div>
            <div class="progress mt-2" style="height:6px;"><div class="progress-bar bg-success" style="width: {{ min($tauxReal, 100) }}%"></div></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small text-uppercase">Reste à mobiliser</div>
            <div class="h4 mb-0 text-danger">{{ $fmt(max($synthese['montant_prevu'] - $synthese['montant_realise'], 0)) }} <small class="text-muted">XAF</small></div>
        </div></div>
    </div>
</div>

@if ($parCategorie->isNotEmpty())
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
    <h6 class="text-muted text-uppercase small mb-3">Mobilisation par catégorie</h6>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Catégorie</th><th class="text-end">Prévu</th><th class="text-end">Réalisé</th><th class="text-end">Taux</th></tr></thead>
        <tbody>
        @foreach ($parCategorie as $row)
            @php $t = $row->prevu > 0 ? round($row->realise / $row->prevu * 100, 1) : 0; @endphp
            <tr>
                <td>{{ $row->categorie?->libelle ?? 'Non catégorisée' }}</td>
                <td class="text-end">{{ $fmt($row->prevu) }}</td>
                <td class="text-end text-success">{{ $fmt($row->realise) }}</td>
                <td class="text-end">{{ $t }} %</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div></div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Référence</th><th>Catégorie</th><th>Origine</th><th>Imputation</th>
                    <th class="text-end">Prévu</th><th class="text-end">Réalisé</th><th class="text-center">Statut</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($recettes as $rec)
                <tr>
                    <td><code>{{ $rec->reference }}</code></td>
                    <td class="small">{{ $rec->categorie?->libelle ?? '—' }}</td>
                    <td class="small">{{ $rec->etatMembre?->pays ?? $rec->sourceFinancement?->libelle ?? '—' }}</td>
                    <td class="small text-muted">{{ $rec->rubrique?->code ?? '—' }}</td>
                    <td class="text-end">{{ $fmt($rec->montant_prevu) }}</td>
                    <td class="text-end text-success">{{ $fmt($rec->montant_realise) }}</td>
                    <td class="text-center"><span class="badge bg-light text-dark border">{{ \App\Models\Recette::STATUTS[$rec->statut] ?? $rec->statut }}</span></td>
                    <td class="text-end"><a href="{{ route('recettes.show', $rec) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Aucune recette pour cet exercice.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $recettes->links() }}</div>
@endsection
