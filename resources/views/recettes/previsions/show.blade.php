@extends('layouts.app')
@section('titre', 'Prévision '.$prevision->reference)

@php $fmt = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route('previsions-recettes.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
    <div class="d-flex gap-2">
        @can('update', $prevision)
            <a href="{{ route('previsions-recettes.edit', $prevision) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>
        @endcan
        @can('reviser', $prevision)
            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#reviserModal"><i class="bi bi-arrow-repeat me-1"></i>Réviser</button>
        @endcan
        @can('delete', $prevision)
            <form method="POST" action="{{ route('previsions-recettes.destroy', $prevision) }}" onsubmit="return confirm('Supprimer cette prévision ?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
        @endcan
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h5 class="mb-0"><code>{{ $prevision->reference }}</code> <span class="text-muted small">v{{ $prevision->version }}</span></h5>
                <span class="badge bg-primary">{{ \App\Models\PrevisionRecette::STATUTS[$prevision->statut] ?? $prevision->statut }}</span>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-muted">Exercice</dt><dd class="col-sm-8">{{ $prevision->exercice?->annee }}</dd>
                <dt class="col-sm-4 text-muted">Catégorie</dt><dd class="col-sm-8">{{ $prevision->categorie?->libelle ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Scénario</dt><dd class="col-sm-8">{{ $prevision->scenario }} @if($prevision->est_courante)<span class="badge bg-warning text-dark ms-1">Courante</span>@endif</dd>
                <dt class="col-sm-4 text-muted">État membre</dt><dd class="col-sm-8">{{ $prevision->etatMembre?->pays ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Source / PTF</dt><dd class="col-sm-8">{{ $prevision->sourceFinancement?->libelle ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Imputation</dt><dd class="col-sm-8">{{ $prevision->rubrique ? $prevision->rubrique->code.' — '.$prevision->rubrique->intitule : '⚠️ Non ventilée' }}</dd>
                <dt class="col-sm-4 text-muted">Recette liée</dt><dd class="col-sm-8">{{ $prevision->recette ? $prevision->recette->reference : '—' }}</dd>
                <dt class="col-sm-4 text-muted">Créée par</dt><dd class="col-sm-8">{{ $prevision->creePar?->name ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted">Observations</dt><dd class="col-sm-8">{{ $prevision->observations ?? '—' }}</dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Montant prévu</div>
            <div class="h5">{{ $fmt($prevision->montant_prevu) }} XAF</div>
            <hr>
            <div class="text-muted small text-uppercase">Montant révisé</div>
            <div class="h5 {{ $prevision->montant_revise !== null ? 'text-warning' : 'text-muted' }}">{{ $prevision->montant_revise !== null ? $fmt($prevision->montant_revise).' XAF' : '—' }}</div>
            <hr>
            <div class="text-muted small text-uppercase">Montant retenu</div>
            <div class="h5 text-primary">{{ $fmt($prevision->montant_retenu) }} XAF</div>
        </div></div>
    </div>
</div>

@can('reviser', $prevision)
<div class="modal fade" id="reviserModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('previsions-recettes.reviser', $prevision) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Réviser la prévision</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Crée une nouvelle version (v{{ $prevision->version + 1 }}) et archive la version courante.</p>
                <div class="mb-3">
                    <label class="form-label">Nouveau montant révisé (XAF) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" name="montant_revise" value="{{ $prevision->montant_revise ?? $prevision->montant_prevu }}" class="form-control" required>
                </div>
                <div class="mb-1">
                    <label class="form-label">Motif / observations</label>
                    <textarea name="observations" rows="2" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button><button class="btn btn-warning">Réviser</button></div>
        </form>
    </div>
</div>
@endcan
@endsection
