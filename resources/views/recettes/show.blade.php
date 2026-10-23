@extends('layouts.app')
@section('titre', 'Recette '.$recette->reference)

@php $fmt = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp

@section('contenu')
<div class="mb-3"><a href="{{ route('recettes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h5 class="mb-0"><code>{{ $recette->reference }}</code></h5>
                <span class="badge bg-primary">{{ \App\Models\Recette::STATUTS[$recette->statut] ?? $recette->statut }}</span>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted">Exercice</dt><dd class="col-sm-8">{{ $recette->exercice?->annee }}</dd>
                <dt class="col-sm-4 text-muted">Catégorie</dt><dd class="col-sm-8">{{ $recette->categorie?->libelle ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">État membre</dt><dd class="col-sm-8">{{ $recette->etatMembre?->pays ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Source / PTF</dt><dd class="col-sm-8">{{ $recette->sourceFinancement?->libelle ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Imputation budgétaire</dt><dd class="col-sm-8">{{ $recette->rubrique ? $recette->rubrique->code.' — '.$recette->rubrique->intitule : '⚠️ Non ventilée' }}</dd>
                <dt class="col-sm-4 text-muted">Prévision liée</dt><dd class="col-sm-8">{{ $recette->prevision ? $recette->prevision->reference : '—' }}</dd>
                <dt class="col-sm-4 text-muted">Observations</dt><dd class="col-sm-8">{{ $recette->observations ?? '—' }}</dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Montant prévu</div>
            <div class="h5">{{ $fmt($recette->montant_prevu) }} XAF</div>
            <hr>
            <div class="text-muted small text-uppercase">Montant réalisé</div>
            <div class="h5 text-success">{{ $fmt($recette->montant_realise) }} XAF</div>
            <hr>
            <div class="text-muted small text-uppercase">Écart</div>
            <div class="h6 {{ $recette->ecart_realisation >= 0 ? 'text-success' : 'text-danger' }}">{{ $fmt($recette->ecart_realisation) }} XAF</div>
            @if ($recette->estVerrouillee())
                <div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-lock me-1"></i>Recette comptabilisée : suppression interdite.</div>
            @endif
        </div></div>

        @can('update', $recette)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-3 me-1"></i>Ventilation budgétaire</div>
            <div class="card-body">
                @unless ($recette->rubrique_id)
                    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>Recette non ventilée : la rubrique est obligatoire avant toute comptabilisation d'encaissement.</div>
                @endunless
                <form method="POST" action="{{ route('recettes.ventiler', $recette) }}" class="d-flex gap-2">
                    @csrf
                    <select name="rubrique_id" class="form-select form-select-sm" required>
                        <option value="">— Rubrique d'imputation</option>
                        @foreach ($rubriques as $rub)
                            <option value="{{ $rub->id }}" @selected($recette->rubrique_id == $rub->id)>{{ $rub->code }} — {{ \Illuminate\Support\Str::limit($rub->intitule, 40) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i></button>
                </form>
            </div>
        </div>
        @endcan
    </div>
</div>
@endsection
