@extends('layouts.app')
@section('titre', 'Appels de fonds & recouvrement')

@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $totAppele = $parEtat->sum('appele');
    $totRegle = $parEtat->sum('regle');
    $tauxGlobal = $totAppele > 0 ? round($totRegle / $totAppele * 100, 1) : 0;
@endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2 flex-wrap" method="GET">
        <select name="exercice" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
            @foreach ($exercices as $ex)
                <option value="{{ $ex->id }}" @selected($exercice && $exercice->id === $ex->id)>Exercice {{ $ex->annee }}</option>
            @endforeach
        </select>
        <select name="statut" class="form-select form-select-sm" style="width:190px;" onchange="this.form.submit()">
            <option value="">Tous statuts</option>
            @foreach (\App\Models\AppelFonds::STATUTS as $k => $v)
                <option value="{{ $k }}" @selected(request('statut')===$k)>{{ $v }}</option>
            @endforeach
        </select>
    </form>
    @can('recette.recouvrement')
        <a href="{{ route('appels-fonds.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvel appel de fonds</a>
    @endcan
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Total appelé (États)</div>
        <div class="h4 mb-0">{{ $fmt($totAppele) }} <small class="text-muted">XAF</small></div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Total recouvré</div>
        <div class="h4 mb-0 text-success">{{ $fmt($totRegle) }} <small class="text-muted">XAF</small></div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Arriérés / taux recouvrement</div>
        <div class="h4 mb-0 text-danger">{{ $fmt(max($totAppele - $totRegle, 0)) }} <small class="text-muted">XAF · {{ $tauxGlobal }}%</small></div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body">
    <h6 class="text-muted text-uppercase small mb-3">Contributions par État membre — exercice {{ $exercice?->annee }}</h6>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>État</th><th class="text-end">Appelé</th><th class="text-end">Recouvré</th><th class="text-end">Arriéré</th><th class="text-end">Taux</th></tr></thead>
        <tbody>
        @foreach ($etats as $etat)
            @php
                $row = $parEtat->get($etat->id);
                $appele = (float) ($row->appele ?? 0);
                $regle = (float) ($row->regle ?? 0);
                $arriere = max($appele - $regle, 0);
                $taux = $appele > 0 ? round($regle / $appele * 100, 1) : 0;
            @endphp
            <tr class="{{ $appele == 0 ? 'text-muted' : '' }}">
                <td>{{ $etat->pays }}</td>
                <td class="text-end">{{ $fmt($appele) }}</td>
                <td class="text-end text-success">{{ $fmt($regle) }}</td>
                <td class="text-end {{ $arriere > 0 ? 'text-danger fw-semibold' : '' }}">{{ $fmt($arriere) }}</td>
                <td class="text-end">@if($appele>0)<span class="badge {{ $taux >= 100 ? 'bg-success' : ($taux > 0 ? 'bg-warning text-dark' : 'bg-danger') }}">{{ $taux }}%</span>@else—@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div></div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Référence</th><th>Émission</th><th>Échéance</th><th>Débiteur</th>
                    <th class="text-end">Appelé</th><th class="text-end">Réglé</th><th class="text-center">Statut</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($appels as $appel)
                <tr>
                    <td><code>{{ $appel->reference }}</code></td>
                    <td class="small">{{ $appel->date_emission?->format('d/m/Y') }}</td>
                    <td class="small">
                        {{ $appel->date_echeance?->format('d/m/Y') ?? '—' }}
                        @if($appel->enRetard())<span class="badge bg-danger ms-1">Retard</span>@endif
                    </td>
                    <td class="small">{{ $appel->etatMembre?->pays ?? $appel->sourceFinancement?->libelle ?? '—' }}</td>
                    <td class="text-end">{{ $fmt($appel->montant_appele) }}</td>
                    <td class="text-end text-success">{{ $fmt($appel->montant_regle) }}</td>
                    <td class="text-center"><span class="badge bg-light text-dark border">{{ \App\Models\AppelFonds::STATUTS[$appel->statut] ?? $appel->statut }}</span></td>
                    <td class="text-end"><a href="{{ route('appels-fonds.show', $appel) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Aucun appel de fonds.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $appels->links() }}</div>
@endsection
