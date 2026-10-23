@extends('layouts.app')
@section('titre', $source->libelle)

@section('contenu')
<div class="d-flex justify-content-between mb-3">
    <div>
        <h5>{{ $source->libelle }}</h5>
        <code>{{ $source->code }}</code> · {{ \App\Models\SourceFinancement::TYPES[$source->type] ?? $source->type }}
        @if($source->classification) · {{ \App\Models\SourceFinancement::CLASSIFICATIONS[$source->classification] }} @endif
    </div>
    @can('source_financement.modifier')<a href="{{ route('sources.edit', $source) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-4">PTF</dt><dd class="col-8">@if($source->partenaire)<a href="{{ route('partenaires.show', $source->partenaire) }}">{{ $source->partenaire->libelle }}</a>@else — @endif</dd>
                <dt class="col-4">Bailleur</dt><dd class="col-8">{{ $source->bailleur ?? '—' }}</dd>
                <dt class="col-4">Lignes budget</dt><dd class="col-8">{{ $source->lignes_budgetaires_count }}</dd>
            </dl>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Conventions liées</div>
            <ul class="list-group list-group-flush">
                @forelse ($source->conventions as $c)
                    <li class="list-group-item small">{{ $c->code }} · {{ $c->libelle }} ({{ number_format($c->montant_accord, 0, ',', ' ') }} XAF)</li>
                @empty
                    <li class="list-group-item text-muted">Aucune convention.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
