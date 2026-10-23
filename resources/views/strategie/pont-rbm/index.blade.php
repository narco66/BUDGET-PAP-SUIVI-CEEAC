@extends('layouts.app')
@section('titre', 'Pont RBM ↔ Stratégie')
@section('contenu')
@include('strategie._nav', ['active' => 'pont-rbm'])
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="small text-muted">Piliers orientés</div><div class="fs-5 fw-bold">{{ $synthese['piliers_orientes_pct'] }}%</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="small text-muted">Activités → projet</div><div class="fs-5 fw-bold">{{ $synthese['activites_projet_pct'] }}%</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-3"><div class="small text-muted">Projets → pilier</div><div class="fs-5 fw-bold">{{ $synthese['projets_pilier_pct'] }}%</div></div></div></div>
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead class="table-light"><tr><th>Pilier</th><th>Exercice</th><th>Orientation</th><th>Rattachement</th></tr></thead>
<tbody>@foreach($piliers as $pilier)<tr>
<td><code>{{ $pilier->code }}</code><br><span class="small text-muted">{{ Str::limit($pilier->intitule, 40) }}</span></td>
<td>{{ $pilier->exercice?->annee ?? '—' }}</td>
<td>{{ $pilier->orientationStrategique?->code ?? '—' }}</td>
<td>@can('strategie.update')<form method="POST" action="{{ route('strategie.pont-rbm.update', $pilier) }}" class="row g-1">@csrf @method('PUT')
<div class="col-md-5"><select name="orientation_strategique_id" class="form-select form-select-sm"><option value="">— Orientation —</option>@foreach($orientations as $o)<option value="{{ $o->id }}" @selected($pilier->orientation_strategique_id===$o->id)>{{ $o->code }}</option>@endforeach</select></div>
<div class="col-md-5"><select name="programme_strategique_id" class="form-select form-select-sm"><option value="">— Programme —</option>@foreach($programmes as $pg)<option value="{{ $pg->id }}" @selected($pg->pilier_id===$pilier->id)>{{ $pg->code }}</option>@endforeach</select></div>
<div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Lier</button></div>
</form>@else — @endcan</td>
</tr>@endforeach</tbody></table></div></div>
<div class="mt-3">{{ $piliers->links() }}</div>
@endsection
