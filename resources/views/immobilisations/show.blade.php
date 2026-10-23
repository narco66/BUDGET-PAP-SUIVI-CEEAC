@extends('layouts.app')
@section('titre', $immobilisation->numero)

@section('contenu')
<a href="{{ route('immobilisations.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Immobilisations</a>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">{{ $immobilisation->designation }}</h4>
        <span class="text-muted small">{{ $immobilisation->numero }} · Exercice {{ $immobilisation->exercice?->annee }}</span>
    </div>
    @can('immobilisation.modifier')
        <a href="{{ route('immobilisations.edit', $immobilisation) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Modifier</a>
    @endcan
</div>

<div class="row g-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Valeur d'acquisition</div>
        <div class="fs-5 fw-bold">{{ number_format($immobilisation->valeur_acquisition, 0, ',', ' ') }} XAF</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Amortissement cumulé</div>
        <div class="fs-5 fw-bold text-warning">{{ number_format($immobilisation->amortissementCumule(), 0, ',', ' ') }} XAF</div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-muted small">Valeur nette comptable</div>
        <div class="fs-5 fw-bold text-success">{{ number_format($immobilisation->valeurNette(), 0, ',', ' ') }} XAF</div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm mt-3"><div class="card-body">
    <dl class="row mb-0 small">
        <dt class="col-sm-3">Catégorie</dt><dd class="col-sm-9">{{ \App\Models\Immobilisation::CATEGORIES[$immobilisation->categorie] ?? $immobilisation->categorie }}</dd>
        <dt class="col-sm-3">Statut</dt><dd class="col-sm-9">{{ \App\Models\Immobilisation::STATUTS[$immobilisation->statut] ?? $immobilisation->statut }}</dd>
        <dt class="col-sm-3">Structure</dt><dd class="col-sm-9">{{ $immobilisation->structure?->libelle ?? '—' }}</dd>
        <dt class="col-sm-3">Date acquisition</dt><dd class="col-sm-9">{{ $immobilisation->date_acquisition?->format('d/m/Y') }}</dd>
        <dt class="col-sm-3">Durée amortissement</dt><dd class="col-sm-9">{{ $immobilisation->duree_amortissement_ans }} ans (linéaire)</dd>
        <dt class="col-sm-3">Marque / série</dt><dd class="col-sm-9">{{ $immobilisation->marque ?? '—' }} · {{ $immobilisation->numero_serie ?? '—' }}</dd>
        <dt class="col-sm-3">Localisation</dt><dd class="col-sm-9">{{ $immobilisation->localisation ?? '—' }}</dd>
        <dt class="col-sm-3">Affectation</dt><dd class="col-sm-9">{{ $immobilisation->affectation ?? '—' }}</dd>
        <dt class="col-sm-3">Compte SYSCOHADA</dt><dd class="col-sm-9">{{ $immobilisation->compte_syscohada ?? '—' }}</dd>
        @if ($immobilisation->engagement)
            <dt class="col-sm-3">Engagement</dt><dd class="col-sm-9">{{ $immobilisation->engagement->numero }} — {{ $immobilisation->engagement->objet }}</dd>
        @endif
        @if ($immobilisation->observations)
            <dt class="col-sm-3">Observations</dt><dd class="col-sm-9">{{ $immobilisation->observations }}</dd>
        @endif
    </dl>
</div></div>
@endsection
