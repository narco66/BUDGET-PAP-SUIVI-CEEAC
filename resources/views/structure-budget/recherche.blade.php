@extends('layouts.app')
@section('titre', 'Recherche — Structure du budget')
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp

@section('contenu')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Recherche multicritère</h4>
        <span class="text-muted small">Lignes budgétaires · exercice {{ $exercice?->annee ?? '—' }}</span>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('structure-budget.arbre-complet', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-secondary btn-sm">Arbre complet</a>
        @can('export', \App\Authorization\StructureBudget::class)
            <a href="{{ route('structure-budget.export', array_merge(['exercice' => $exercice?->id, 'format' => 'xlsx'], $filtres)) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
            <a href="{{ route('structure-budget.import-export', ['exercice' => $exercice?->id]) }}" class="btn btn-outline-secondary btn-sm">Import/Export</a>
        @endcan
    </div>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <input type="hidden" name="exercice" value="{{ $exercice?->id }}">
        <div class="col-md-3">
            <label class="form-label small mb-0">Texte (code, intitulé…)</label>
            <input name="q" class="form-control form-control-sm" value="{{ $filtres['q'] ?? '' }}" placeholder="Rechercher…">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">Structure</label>
            <select name="structure" class="form-select form-select-sm">
                <option value="">— Toutes —</option>
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected(($filtres['structure'] ?? '') == $s->id)>{{ $s->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">Pilier (PAP)</label>
            <select name="pilier" class="form-select form-select-sm">
                <option value="">— Tous —</option>
                @foreach ($piliers as $p)
                    <option value="{{ $p->id }}" @selected(($filtres['pilier'] ?? '') == $p->id)>{{ $p->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">Rubrique</label>
            <select name="rubrique" class="form-select form-select-sm">
                <option value="">— Toutes —</option>
                @foreach ($rubriques as $r)
                    <option value="{{ $r->id }}" @selected(($filtres['rubrique'] ?? '') == $r->id)>{{ $r->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-0">Source</label>
            <select name="source" class="form-select form-select-sm">
                <option value="">— Toutes —</option>
                @foreach ($sources as $sf)
                    <option value="{{ $sf->id }}" @selected(($filtres['source'] ?? '') == $sf->id)>{{ $sf->code }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1">
            <button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Code</th><th>Intitulé</th><th>Structure</th><th>Rubrique</th><th>Pilier</th><th>Activité</th>
                <th class="text-end">Ouvert</th><th class="text-end">Engagé</th><th></th>
            </tr>
        </thead>
        <tbody>
        @forelse ($lignes as $l)
            <tr class="{{ !($l->actif ?? true) ? 'table-secondary' : '' }}">
                <td class="font-monospace small"><a href="{{ route('structure-budget.ligne.show', ['ligne' => $l->id, 'exercice' => $exercice?->id]) }}">{{ $l->code }}</a></td>
                <td class="small">{{ \Illuminate\Support\Str::limit($l->intitule, 36) }}</td>
                <td class="small">{{ $l->structure_libelle }}</td>
                <td class="font-monospace small">{{ $l->rubrique_code }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($l->pilier_intitule, 20) }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($l->activite_intitule, 20) }}</td>
                <td class="text-end small">{{ $fmt($l->montant_ouvert) }}</td>
                <td class="text-end small text-primary">{{ $fmt($l->montant_engage) }}</td>
                <td class="text-end">
                    @can('structureBudget.updateLigne', \App\Models\LigneBudgetaire::find($l->id))
                        <a href="{{ route('structure-budget.ligne.edit', $l->id) }}" class="btn btn-link btn-sm p-0" title="Modifier"><i class="bi bi-pencil"></i></a>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-4">Aucune ligne ne correspond aux critères.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if($lignes instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $lignes->hasPages())
    <div class="card-footer bg-white">{{ $lignes->links() }}</div>
@endif
</div>
@endsection
