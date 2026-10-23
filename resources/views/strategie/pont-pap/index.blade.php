@extends('layouts.app')

@section('titre', 'Pont Programmation ↔ PAP')

@section('contenu')

@include('strategie._nav', ['active' => 'pont-pap'])

<div class="d-flex flex-wrap justify-content-between mb-3 gap-2">

    <form method="GET" class="d-flex gap-2">

        <select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">

            @foreach($exercices as $ex)<option value="{{ $ex->id }}" @selected($exerciceId==$ex->id)>{{ $ex->annee }}</option>@endforeach

        </select>

    </form>

    @can('strategie.update')

    @if($exerciceId)

    <form method="POST" action="{{ route('strategie.pont-pap.synchroniser') }}">@csrf<input type="hidden" name="exercice" value="{{ $exerciceId }}">

    <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Synchroniser liaisons PAP</button></form>

    @endif

    @endcan

</div>



<div class="row g-3 mb-4">

    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">Programmations liées</div><div class="fs-5 fw-bold">{{ $reconciliation['programmations_liees'] }}/{{ $reconciliation['programmations_total'] }}</div>

    </div></div></div>

    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">PAP exercice</div><div class="fs-6 fw-bold">{{ $reconciliation['pap']?->libelle ?? '—' }}</div>

    </div></div></div>

    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-3 text-center">

        <div class="text-muted small">Taux liaison</div><div class="fs-5 fw-bold text-{{ $reconciliation['taux_liaison_prog'] >= 100 ? 'success' : 'warning' }}">{{ $reconciliation['taux_liaison_prog'] }}%</div>

    </div></div></div>

</div>



<div class="card border-0 shadow-sm mb-3"><div class="table-responsive"><table class="table table-hover mb-0">

<thead class="table-light"><tr><th>Exercice</th><th>Pluriannuelle</th><th>Statut</th><th>PAP rattaché</th><th class="text-end">Action</th></tr></thead>

<tbody>@forelse($reconciliation['programmations'] as $prog)<tr>

<td>{{ $prog->exercice?->annee }}</td><td>{{ $prog->programmationPluriannuelle?->reference }}</td>

<td>@include('strategie._statut', ['statut' => $prog->statut])</td>

<td>@if($prog->pap)<a href="{{ route('paps.show', $prog->pap) }}">{{ Str::limit($prog->pap->libelle, 40) }}</a>@else<span class="text-danger">Non lié</span>@endif</td>

<td class="text-end">@can('strategie.update')<a href="{{ route('strategie.programmations-annuelles.show', $prog) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>@endcan</td>

</tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Aucune programmation pour cet exercice.</td></tr>@endforelse</tbody></table></div></div>



@if($sansPap->isNotEmpty())

<div class="card border-0 shadow-sm"><div class="card-header bg-white text-warning">Programmations sans PAP (tous exercices)</div>

<ul class="list-group list-group-flush small">@foreach($sansPap as $p)

<li class="list-group-item">{{ $p->exercice?->annee }} — {{ $p->programmationPluriannuelle?->reference }}</li>

@endforeach</ul></div>

@endif

@endsection

