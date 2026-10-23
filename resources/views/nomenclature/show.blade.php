@extends('layouts.app')
@section('titre', $label.' '.$element->code)
@php
    $childNiveau = ['titre' => 'article', 'article' => 'paragraphe', 'paragraphe' => 'rubrique', 'rubrique' => null][$niveau];
    $childRel = ['titre' => 'articles', 'article' => 'paragraphes', 'paragraphe' => 'rubriques', 'rubrique' => 'lignesBudgetaires'][$niveau];
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
@endphp

@section('contenu')
<a href="{{ route('nomenclature.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Nomenclature</a>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">{{ $label }} <span class="font-monospace text-primary">{{ $element->code }}</span>
        @if (($element->statut ?? 'valide') === 'valide')<span class="badge bg-success-subtle text-success-emphasis align-middle">Validé</span>
        @else<span class="badge bg-warning-subtle text-warning-emphasis align-middle">Brouillon</span>@endif
    </h4>
    <div class="d-flex gap-2">
        @if (! $element->trashed())
            @if (($element->statut ?? 'valide') !== 'valide')
                @can('nomenclature.valider')<form method="POST" action="{{ route('nomenclature.valider', ['niveau' => $niveau, 'id' => $element->id]) }}">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check2-circle me-1"></i>Valider</button></form>@endcan
            @endif
            @can('nomenclature.modifier')<a href="{{ route('nomenclature.edit', ['niveau' => $niveau, 'id' => $element->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
        @else
            <span class="badge bg-warning text-dark">Archivé</span>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <table class="table table-sm mb-0">
        @if ($niveau === 'titre')<tr><th class="text-muted" style="width:25%">Sens</th><td>{{ \App\Models\BudgetTitre::SENS[$element->sens] ?? $element->sens }}</td></tr>@endif
        @if ($niveau === 'rubrique')<tr><th class="text-muted">Chemin</th><td class="font-monospace small">{{ $element->paragraphe?->article?->titre?->code }} / {{ $element->paragraphe?->article?->code }} / {{ $element->paragraphe?->code }} / {{ $element->code }}</td></tr>@endif
        @if ($niveau === 'paragraphe')<tr><th class="text-muted">Article parent</th><td>{{ $element->article?->code }} — {{ $element->article?->intitule }}</td></tr>@endif
        @if ($niveau === 'article')<tr><th class="text-muted">Chapitre parent</th><td>{{ $element->titre?->code }} — {{ $element->titre?->intitule }}</td></tr>@endif
        <tr><th class="text-muted">Code</th><td class="font-monospace">{{ $element->code }}</td></tr>
        <tr><th class="text-muted">Intitulé</th><td>{{ $element->intitule }}</td></tr>
        <tr><th class="text-muted">Ordre</th><td>{{ $element->ordre }}</td></tr>
        <tr><th class="text-muted">Statut</th><td>{{ \App\Models\BudgetTitre::STATUTS[$element->statut ?? 'valide'] ?? 'Validé' }}</td></tr>
    </table>
</div></div>

<h6 class="fw-bold">{{ $niveau === 'rubrique' ? 'Lignes budgétaires rattachées' : 'Éléments rattachés' }}</h6>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    @if ($niveau === 'rubrique')
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th>Exercice</th><th class="text-end">Ouvert</th><th class="text-center">Statut</th></tr></thead>
            <tbody>
            @forelse ($element->lignesBudgetaires as $l)
                <tr><td class="font-monospace small">{{ $l->code }}</td><td>{{ \Illuminate\Support\Str::limit($l->intitule, 40) }}</td>
                    <td>{{ $l->exercice?->annee ?? '—' }}</td><td class="text-end">{{ $fmt($l->montant_ouvert) }}</td>
                    <td class="text-center">@if($l->actif)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td></tr>
            @empty<tr><td colspan="5" class="text-center text-muted py-3">Aucune ligne budgétaire.</td></tr>@endforelse
            </tbody>
        </table>
    @else
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Code</th><th>Intitulé</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($element->$childRel as $enfant)
                <tr><td class="font-monospace small">{{ $enfant->code }}</td><td>{{ $enfant->intitule }}</td>
                    <td class="text-end"><a href="{{ route('nomenclature.show', ['niveau' => $childNiveau, 'id' => $enfant->id]) }}" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-eye"></i></a></td></tr>
            @empty<tr><td colspan="3" class="text-center text-muted py-3">Aucun élément rattaché.</td></tr>@endforelse
            </tbody>
        </table>
    @endif
</div></div>
@endsection
