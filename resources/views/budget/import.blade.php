@extends('layouts.app')
@section('titre', 'Import budgétaire')

@section('contenu')
@if(session('rejets'))
<div class="alert alert-warning"><strong>Lignes rejetées :</strong><ul class="mb-0 small">@foreach(session('rejets') as $r)<li>{{ $r }}</li>@endforeach</ul></div>
@endif

@if(session('rapport_nomenclature'))
@php($rap = session('rapport_nomenclature'))
<div class="alert alert-info">
    <strong><i class="bi bi-clipboard-check me-1"></i>Rapport d'import nomenclature :</strong>
    {{ $rap['crees'] }} créée(s), {{ $rap['majs'] }} mise(s) à jour, {{ $rap['rejets_total'] }} rejetée(s).
    @if($rap['rejets'])
    <ul class="mb-0 small mt-2">@foreach($rap['rejets'] as $r)<li>Ligne {{ $r['ligne'] }} — {{ $r['motif'] }}</li>@endforeach
        @if($rap['rejets_total'] > count($rap['rejets']))<li class="text-muted">… {{ $rap['rejets_total'] - count($rap['rejets']) }} autre(s)</li>@endif
    </ul>
    @endif
</div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-list-columns me-1"></i>Nomenclature budgétaire</div>
            <div class="card-body">
                <p class="small text-muted">Format à plat : une rubrique par ligne. <a href="{{ route('budget.modele.nomenclature') }}">Télécharger le modèle</a>.</p>
                @can('nomenclature.creer')
                <form action="{{ route('budget.import.nomenclature.apercu') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="fichier" class="form-control mb-2" accept=".xlsx,.xls,.csv" required>
                    <button class="btn btn-primary w-100"><i class="bi bi-eye me-1"></i>Prévisualiser puis importer</button>
                    <p class="form-text mb-0">Aperçu des données et rapport d'import (créés / mis à jour / rejetés) avant validation.</p>
                </form>
                @else<p class="text-muted small">Droit requis : nomenclature.creer</p>@endcan
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-cash-stack me-1"></i>Lignes budgétaires (Budget {{ now()->year }})</div>
            <div class="card-body">
                <p class="small text-muted">Les rubriques doivent exister. <a href="{{ route('budget.modele.lignes') }}">Télécharger le modèle</a>.</p>
                @can('ligne_budgetaire.creer')
                <form action="{{ route('budget.import.lignes') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <select name="exercice_id" class="form-select mb-2" required>
                        @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected($ex->statut==='ouvert')>Exercice {{ $ex->annee }}</option>@endforeach
                    </select>
                    <input type="file" name="fichier" class="form-control mb-2" accept=".xlsx,.xls,.csv" required>
                    <button class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Importer les lignes</button>
                </form>
                @else<p class="text-muted small">Droit requis : ligne_budgetaire.creer</p>@endcan
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-diagram-3-fill me-1"></i>Détail du PAP (chaîne RBM)</div>
            <div class="card-body">
                <p class="small text-muted">Pilier → Axe → Produit → Sous-Produit → Activité (montants CEEAC/PTF en XAF). Colonne optionnelle <code>ligne_code</code> pour lier au budget. <a href="{{ route('budget.modele.pap') }}">Télécharger le modèle</a>.</p>
                @can('rbm.creer')
                <form action="{{ route('budget.import.pap') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <select name="exercice_id" class="form-select mb-2" required>
                        @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected(($exercice?->id ?? null) === $ex->id)>Exercice {{ $ex->annee }}</option>@endforeach
                    </select>
                    <input type="file" name="fichier" class="form-control mb-2" accept=".xlsx,.xls,.csv" required>
                    <button class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Importer le détail PAP</button>
                </form>
                @else<p class="text-muted small">Droit requis : rbm.creer</p>@endcan
            </div>
        </div>
    </div>
    @if($exercice && $rapprochement)
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-arrow-left-right me-1"></i>Rapprochement PAP ↔ Budget</div>
            <div class="card-body">
                <p class="small text-muted mb-2">Exercice {{ $exercice->annee }} — compare activités PAP et lignes budgétaires après imports séparés.</p>
                <div class="row g-2 mb-3 small">
                    <div class="col-6"><span class="text-muted">Activités budgétisées</span><div class="fw-semibold">{{ $rapprochement['synthese']['activites_liees'] }} / {{ $rapprochement['synthese']['activites_avec_montant'] }}</div></div>
                    <div class="col-6"><span class="text-muted">Écarts détectés</span><div class="fw-semibold {{ $rapprochement['ecarts_total'] ? 'text-warning' : 'text-success' }}">{{ $rapprochement['ecarts_total'] }}</div></div>
                    <div class="col-12"><span class="text-muted">Total PAP vs lignes liées</span><div class="fw-semibold">{{ number_format($rapprochement['synthese']['total_montant_pap'], 0, ',', ' ') }} / {{ number_format($rapprochement['synthese']['total_montant_lignes_liees'], 0, ',', ' ') }} XAF</div></div>
                </div>
                @if($rapprochement['ecarts_total'] > 0)
                <ul class="small mb-3">
                    @foreach(array_slice($rapprochement['ecarts'], 0, 5) as $ecart)
                    <li><code>{{ $ecart['type'] }}</code> — {{ $ecart['activite_code'] }}@if($ecart['ligne_code']) ↔ {{ $ecart['ligne_code'] }}@endif</li>
                    @endforeach
                    @if($rapprochement['ecarts_total'] > 5)<li class="text-muted">… {{ $rapprochement['ecarts_total'] - 5 }} autre(s)</li>@endif
                </ul>
                @else
                <div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i>PAP et budget alignés.</div>
                @endif
                @if(auth()->user()->can('ligne_budgetaire.modifier') || auth()->user()->can('rbm.modifier'))
                <form action="{{ route('budget.rapprochement.executer') }}" method="POST">
                    @csrf
                    <input type="hidden" name="exercice_id" value="{{ $exercice->id }}">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="aligner_montants" value="1" id="aligner_montants">
                        <label class="form-check-label small" for="aligner_montants">Aligner <code>montant_ouvert</code> sur le PAP (lignes sans engagement)</label>
                    </div>
                    <button class="btn btn-outline-primary w-100"><i class="bi bi-link-45deg me-1"></i>Lancer le rapprochement</button>
                </form>
                <code class="small d-block mt-2">php artisan budget:rapprocher-pap --exercice={{ $exercice->annee }}</code>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>

<div class="mt-3"><a href="{{ route('budget.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Retour au budget</a></div>
@endsection
