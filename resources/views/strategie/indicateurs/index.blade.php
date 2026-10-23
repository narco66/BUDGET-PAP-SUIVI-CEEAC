@extends('layouts.app')

@section('titre', 'Indicateurs stratégiques')

@section('contenu')

@include('strategie._nav', ['active' => 'indicateurs'])

<div class="d-flex justify-content-between mb-3">

    @can('strategie.export')<a href="{{ route('strategie.export.excel', 'indicateurs') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-excel me-1"></i>Export</a>@endcan

    @can('strategie.create')<a href="{{ route('strategie.indicateurs.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg me-1"></i>Nouvel indicateur</a>@endcan

</div>

@include('strategie._filtres', ['statuts' => ['actif','inactif','archive']])

<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">

<thead class="table-light"><tr><th>Code</th><th>Libellé</th><th>Objectif</th><th>Cible</th><th>RBM</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>

<tbody>@forelse($indicateurs as $i)<tr>

<td><code>{{ $i->code }}</code></td><td>{{ Str::limit($i->libelle, 40) }}</td>

<td>{{ $i->objectifStrategique?->code ?? '—' }}</td><td>{{ $i->valeur_cible ?? '—' }} {{ $i->unite }}</td>

<td>{{ $i->indicateurRbm?->code ?? '—' }}</td><td>@include('strategie._statut', ['statut' => $i->statut])</td>

<td class="text-end">@can('strategie.view')<a href="{{ route('strategie.indicateurs.show', $i) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>@endcan

@can('strategie.update')<a href="{{ route('strategie.indicateurs.edit', $i) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan

@include('strategie._actions', ['item' => $i, 'entite' => 'indicateurs', 'destroyRoute' => route('strategie.indicateurs.destroy', $i)])</td>

</tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">Aucun indicateur.</td></tr>@endforelse</tbody></table></div></div>

<div class="mt-3">{{ $indicateurs->links() }}</div>

@endsection

