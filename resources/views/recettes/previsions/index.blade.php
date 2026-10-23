@extends('layouts.app')
@section('titre', 'Prévisions de recettes')

@php $fmt = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2 flex-wrap" method="GET">
        <select name="exercice" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
            <option value="">Tous exercices</option>
            @foreach ($exercices as $ex)
                <option value="{{ $ex->id }}" @selected(request('exercice')==$ex->id)>Exercice {{ $ex->annee }}</option>
            @endforeach
        </select>
        <select name="statut" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
            <option value="">Tous statuts</option>
            @foreach (\App\Models\PrevisionRecette::STATUTS as $k => $v)
                <option value="{{ $k }}" @selected(request('statut')===$k)>{{ $v }}</option>
            @endforeach
        </select>
        <div class="form-check form-switch align-self-center ms-1">
            <input class="form-check-input" type="checkbox" name="courantes" value="1" id="sw" @checked(request()->boolean('courantes', true)) onchange="this.form.submit()">
            <label class="form-check-label small" for="sw">Versions courantes</label>
        </div>
    </form>
    @can('recette.creer')
        <a href="{{ route('previsions-recettes.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle prévision</a>
    @endcan
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Référence</th><th>Ex.</th><th>Catégorie</th><th>Origine</th><th class="text-center">V.</th>
                    <th class="text-end">Prévu</th><th class="text-end">Révisé</th><th class="text-center">Statut</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($previsions as $prev)
                <tr>
                    <td><code>{{ $prev->reference }}</code></td>
                    <td>{{ $prev->exercice?->annee }}</td>
                    <td class="small">{{ $prev->categorie?->libelle ?? '—' }}</td>
                    <td class="small">{{ $prev->etatMembre?->pays ?? $prev->sourceFinancement?->libelle ?? '—' }}</td>
                    <td class="text-center">{{ $prev->version }}@if($prev->est_courante)<i class="bi bi-star-fill text-warning ms-1" title="Courante" style="font-size:.7rem"></i>@endif</td>
                    <td class="text-end">{{ $fmt($prev->montant_prevu) }}</td>
                    <td class="text-end">{{ $prev->montant_revise !== null ? $fmt($prev->montant_revise) : '—' }}</td>
                    <td class="text-center"><span class="badge bg-light text-dark border">{{ \App\Models\PrevisionRecette::STATUTS[$prev->statut] ?? $prev->statut }}</span></td>
                    <td class="text-end"><a href="{{ route('previsions-recettes.show', $prev) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Aucune prévision de recette.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $previsions->links() }}</div>
@endsection
