@extends('layouts.app')
@section('titre', 'Lignes — '.$structure->code)
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp

@section('contenu')
<a href="{{ route('structure-budget.arbre', ['exercice' => $exercice?->id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Structure du budget</a>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><span class="sb-code font-monospace text-primary">{{ $structure->code }}</span> — {{ $structure->libelle }}</h4>
        <span class="text-muted small text-capitalize">{{ str_replace('_', ' ', $structure->type) }} · Exercice {{ $exercice?->annee ?? '—' }}</span>
    </div>
    @can('create', \App\Authorization\StructureBudget::class)<a href="{{ route('structure-budget.wizard', ['exercice' => $exercice?->id, 'structure' => $structure->id]) }}" class="btn btn-success btn-sm"><i class="bi bi-plus-lg me-1"></i>Ajouter une ligne</a>@endcan
</div>

<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th>Rubrique</th><th>Activité</th><th>Source</th>
            <th class="text-end">Ouvert</th><th class="text-end">Engagé</th><th class="text-end">Payé</th></tr></thead>
        <tbody>
        @forelse ($lignes as $l)
            <tr>
                <td class="font-monospace small"><a href="{{ route('structure-budget.ligne.show', ['ligne' => $l->id, 'exercice' => $exercice?->id]) }}">{{ $l->code }}</a></td>
                <td class="small">{{ \Illuminate\Support\Str::limit($l->intitule, 32) }}</td>
                <td class="font-monospace small">{{ $l->rubrique }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($l->activite, 24) }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($l->source, 18) }}</td>
                <td class="text-end small">{{ $fmt($l->montant_ouvert) }}</td>
                <td class="text-end small text-primary">{{ $fmt($l->montant_engage) }}</td>
                <td class="text-end small">{{ $fmt($l->montant_paye) }}</td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">Aucune ligne rattachée à cette structure pour cet exercice.</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
@endsection
