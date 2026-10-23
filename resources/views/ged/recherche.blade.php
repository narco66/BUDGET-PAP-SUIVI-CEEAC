@extends('layouts.app')
@section('titre', 'Recherche avancée GED')

@section('contenu')
@include('ged._nav-module', ['page' => 'recherche'])

<h4 class="fw-bold mb-3">Recherche avancée</h4>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2">
        <div class="col-md-4"><label class="form-label small">Texte libre</label><input type="text" name="q" class="form-control form-control-sm" value="{{ $filtres['q'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label small">Référence</label><input type="text" name="reference" class="form-control form-control-sm" value="{{ $filtres['reference'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label small">Catégorie</label>
            <select name="categorie_id" class="form-select form-select-sm"><option value="">—</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filtres['categorie_id'] ?? '') == $c->id)>{{ $c->libelle }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small">Type</label>
            <select name="type_document_id" class="form-select form-select-sm"><option value="">—</option>
                @foreach($types as $t)<option value="{{ $t->id }}" @selected(($filtres['type_document_id'] ?? '') == $t->id)>{{ $t->libelle }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small">Module</label><input type="text" name="module_source" class="form-control form-control-sm" value="{{ $filtres['module_source'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label small">Statut</label>
            <select name="statut" class="form-select form-select-sm"><option value="">—</option>
                @foreach($statuts as $v => $l)<option value="{{ $v }}" @selected(($filtres['statut'] ?? '') === $v)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small">Confidentialité</label>
            <select name="niveau_confidentialite" class="form-select form-select-sm"><option value="">—</option>
                @foreach($niveaux as $v => $l)<option value="{{ $v }}" @selected(($filtres['niveau_confidentialite'] ?? '') === $v)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small">Dépôt du</label><input type="date" name="date_depot_debut" class="form-control form-control-sm" value="{{ $filtres['date_depot_debut'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label small">Dépôt au</label><input type="date" name="date_depot_fin" class="form-control form-control-sm" value="{{ $filtres['date_depot_fin'] ?? '' }}"></div>
        <div class="col-md-2"><label class="form-label small">Tag</label><input type="text" name="tag" class="form-control form-control-sm" value="{{ $filtres['tag'] ?? '' }}"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Rechercher</button></div>
    </div>
</form>

@include('ged.partials._liste-documents', ['documents' => $documents])

@if(isset($recherchesSauvegardees) && $recherchesSauvegardees->isNotEmpty())
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-bold">Recherches sauvegardées</div>
    <ul class="list-group list-group-flush">
        @foreach($recherchesSauvegardees as $rs)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <a href="{{ route('ged.recherche', $rs->filtres) }}">{{ $rs->nom }}</a>
                @if($rs->par_defaut)<span class="badge bg-primary">Défaut</span>@endif
                <form method="POST" action="{{ route('ged.recherche.supprimer', $rs) }}" class="d-inline">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
@endif

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-bold">Sauvegarder cette recherche</div>
    <div class="card-body">
        <form method="POST" action="{{ route('ged.recherche.sauvegarder') }}" class="row g-2 align-items-end">
            @csrf
            @foreach($filtres as $k => $v)
                @if($v !== null && $v !== '')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
            @endforeach
            <div class="col-md-4"><input type="text" name="nom" class="form-control form-control-sm" placeholder="Nom de la recherche" required></div>
            <div class="col-md-3 form-check"><input type="checkbox" name="par_defaut" value="1" class="form-check-input" id="par_defaut"><label class="form-check-label small" for="par_defaut">Par défaut</label></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary">Sauvegarder</button></div>
        </form>
    </div>
</div>
@endsection
