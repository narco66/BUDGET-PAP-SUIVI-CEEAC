@extends('layouts.app')
@section('titre', 'Recommandations')
@php
    $badge = ['en_attente' => 'secondary', 'en_cours' => 'info', 'realisee' => 'success', 'non_realisee' => 'danger', 'abandonnee' => 'dark'];
@endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Suivi des recommandations</h4>
    @can('recommandation.creer')<a href="{{ route('recommandations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle recommandation</a>@endcan
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto"><select name="exercice" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Tous exercices</option>
        @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected(request('exercice') == $ex->id)>{{ $ex->annee }}</option>@endforeach
    </select></div>
    <div class="col-auto"><select name="source" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Toutes sources</option>
        @foreach ($sources as $k => $v)<option value="{{ $k }}" @selected(request('source') === $k)>{{ $v }}</option>@endforeach
    </select></div>
    <div class="col-auto"><select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">Tous statuts</option>
        @foreach ($statuts as $k => $v)<option value="{{ $k }}" @selected(request('statut') === $k)>{{ $v }}</option>@endforeach
    </select></div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
            <th>N°</th><th>Source</th><th>Libellé</th><th>Échéance</th><th class="text-center">Priorité</th>
            <th style="width:120px">Exécution</th><th class="text-center">Statut</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse ($recommandations as $r)
            <tr>
                <td class="font-monospace small">{{ $r->numero }}</td>
                <td class="small">{{ \App\Models\Recommandation::SOURCES[$r->source] ?? $r->source }}</td>
                <td>{{ \Illuminate\Support\Str::limit($r->libelle, 45) }}</td>
                <td class="small">{{ optional($r->echeance)->format('d/m/Y') ?? '—' }}</td>
                <td class="text-center"><span class="badge bg-{{ $r->priorite === 'haute' ? 'danger' : ($r->priorite === 'moyenne' ? 'warning' : 'secondary') }}">{{ \App\Models\Recommandation::PRIORITES[$r->priorite] }}</span></td>
                <td><div class="progress" style="height:14px"><div class="progress-bar bg-success" style="width:{{ $r->taux_execution }}%">{{ (int) $r->taux_execution }}%</div></div></td>
                <td class="text-center"><span class="badge bg-{{ $badge[$r->statut] ?? 'secondary' }}">{{ \App\Models\Recommandation::STATUTS[$r->statut] ?? $r->statut }}</span></td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('recommandations.show', $r) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a>
                    @can('recommandation.modifier')<a href="{{ route('recommandations.edit', $r) }}" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-pencil"></i></a>@endcan
                    @can('recommandation.supprimer')<form action="{{ route('recommandations.destroy', $r) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-0"><i class="bi bi-trash"></i></button></form>@endcan
                </td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">Aucune recommandation.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $recommandations->links() }}</div>
@endsection
