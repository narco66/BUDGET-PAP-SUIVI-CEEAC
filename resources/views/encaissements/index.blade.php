@extends('layouts.app')
@section('titre', 'Encaissements')

@php $fmt = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2 flex-wrap" method="GET">
        <select name="statut" class="form-select form-select-sm" style="width:170px;" onchange="this.form.submit()">
            <option value="">Tous statuts</option>
            @foreach (\App\Models\Encaissement::STATUTS as $k => $v)
                <option value="{{ $k }}" @selected(request('statut')===$k)>{{ $v }}</option>
            @endforeach
        </select>
    </form>
    @can('recette.encaissement')
        <a href="{{ route('encaissements.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvel encaissement</a>
    @endcan
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Encaissé comptabilisé</div>
        <div class="h4 mb-0 text-success">{{ $fmt($kpis['comptabilise']) }} <small class="text-muted">XAF</small></div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">En attente de validation</div>
        <div class="h4 mb-0 text-warning">{{ $kpis['en_attente'] }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small text-uppercase">Rapprochés</div>
        <div class="h4 mb-0">{{ $kpis['rapproches'] }}</div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Référence</th><th>Date</th><th>Recette</th><th>Mode</th>
                    <th class="text-end">Montant</th><th class="text-end">Contre-valeur XAF</th><th class="text-center">Statut</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($encaissements as $enc)
                <tr>
                    <td><code>{{ $enc->reference }}</code></td>
                    <td class="small">{{ $enc->date_encaissement?->format('d/m/Y') }}</td>
                    <td class="small">{{ $enc->recette?->reference }}</td>
                    <td class="small">{{ \App\Models\Encaissement::MODES_PAIEMENT[$enc->mode_paiement] ?? $enc->mode_paiement }}</td>
                    <td class="text-end">{{ $fmt($enc->montant) }} {{ $enc->devise }}</td>
                    <td class="text-end text-success">{{ $fmt($enc->montant_xaf) }}</td>
                    <td class="text-center">
                        @php $badge = ['saisi'=>'bg-warning text-dark','valide'=>'bg-success','rapproche'=>'bg-primary','annule'=>'bg-secondary'][$enc->statut] ?? 'bg-light text-dark'; @endphp
                        <span class="badge {{ $badge }}">{{ \App\Models\Encaissement::STATUTS[$enc->statut] ?? $enc->statut }}</span>
                    </td>
                    <td class="text-end"><a href="{{ route('encaissements.show', $enc) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Aucun encaissement.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $encaissements->links() }}</div>
@endsection
