@extends('layouts.app')
@section('titre', $partenaire->libelle)

@section('contenu')
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-1">{{ $partenaire->libelle }} @if($partenaire->sigle)<span class="badge bg-light text-dark">{{ $partenaire->sigle }}</span>@endif</h5>
        <div class="text-muted small">Code <code>{{ $partenaire->code }}</code> · {{ \App\Models\PartenaireFinancier::TYPES[$partenaire->type] ?? $partenaire->type }}</div>
    </div>
    <div class="d-flex gap-2">
        @can('partenaire_financier.modifier')<a href="{{ route('partenaires.edit', $partenaire) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>@endcan
        @can('convention_financement.creer')<a href="{{ route('conventions.create', $partenaire) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Convention</a>@endcan
    </div>
</div>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Sources de financement</div>
            <ul class="list-group list-group-flush">
                @forelse ($partenaire->sources as $s)
                    <li class="list-group-item d-flex justify-content-between">
                        <span><code>{{ $s->code }}</code> {{ $s->libelle }}</span>
                        <a href="{{ route('sources.show', $s) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Aucune source rattachée.</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Conventions & accords</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Libellé</th><th>Accord</th><th>Montant</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($partenaire->conventions as $c)
                        <tr>
                            <td><code>{{ $c->code }}</code></td>
                            <td>{{ $c->libelle }}</td>
                            <td class="small">{{ $c->reference_accord ?? '—' }}</td>
                            <td class="text-end">{{ number_format($c->montant_accord, 0, ',', ' ') }}</td>
                            <td>{{ \App\Models\ConventionFinancement::STATUTS[$c->statut] ?? $c->statut }}</td>
                            <td class="text-end">
                                <a href="{{ route('conventions.show', $c) }}" class="btn btn-sm btn-outline-secondary" title="Décaissements"><i class="bi bi-eye"></i></a>
                                @can('convention_financement.modifier')<a href="{{ route('conventions.edit', $c) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">Aucune convention.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
