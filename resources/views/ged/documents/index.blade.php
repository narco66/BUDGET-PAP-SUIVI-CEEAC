@extends('layouts.app')
@section('titre', 'Documents GED')
@php
use App\Services\Ged\GedSearchService;
$f = fn (string $cle) => (string) ($filtres[$cle] ?? '');
$vue = $f('vue');
$lien = fn (array $params) => route('ged.documents.index', array_merge(request()->except('page'), $params));
$avances = collect(['module_source', 'categorie_id', 'type_document_id', 'extension', 'date_depot_debut', 'date_depot_fin'])->contains(fn ($c) => $f($c) !== '');
$actifs = collect($filtres)->except(['tri', 'ordre', 'par_page', 'page'])->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
$volume = fn (int $o) => $o >= 1048576 ? number_format($o / 1048576, 1, ',', ' ').' Mo' : number_format($o / 1024, 0, ',', ' ').' Ko';
$cartes = [
    '' => ['Tous', 'collection', 'primary', $indicateurs['total']],
    'signes' => ['Signés', 'patch-check', 'success', $indicateurs['signes']],
    'archives' => ['Archivés', 'archive', 'secondary', $indicateurs['archives']],
    'confidentiels' => ['Confidentiels', 'shield-lock', 'danger', $indicateurs['confidentiels']],
    'expirant' => ['Expirent sous 30 j', 'calendar-x', 'warning', $indicateurs['expirant']],
    'mes' => ['Mes dépôts', 'person', 'info', $indicateurs['mes']],
];
@endphp

@section('contenu')
@include('ged._nav-module', ['page' => 'documents'])

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1">Registre documentaire</h4>
        <span class="text-muted small">{{ $indicateurs['total'] }} document(s) accessibles · {{ $volume($indicateurs['volume']) }}</span>
    </div>
    <div class="d-flex gap-2">
        @can('ged.exporter')
        <a href="{{ route('ged.exports.documents.excel', request()->query()) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Archiver un registre officiel traçable (versioning, hash, QR)">
                <i class="bi bi-shield-check me-1"></i>Archiver (officiel)
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach (['documents' => 'Registre des documents', 'archives' => 'Registre des archives', 'signatures' => 'Registre des signatures'] as $cle => $libelle)
                    <li><a class="dropdown-item" href="{{ route('ged.exports.registres.archiver', $cle) }}"><i class="bi bi-file-earmark-check me-2"></i>{{ $libelle }}</a></li>
                @endforeach
                @can('ged.consulter_journal')
                <li><a class="dropdown-item" href="{{ route('ged.exports.registres.archiver', 'journal') }}"><i class="bi bi-file-earmark-check me-2"></i>Journal documentaire</a></li>
                @endcan
            </ul>
        </div>
        @endcan
        @can('create', \App\Models\Ged\GedDocument::class)
        <a href="{{ route('ged.documents.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau</a>
        @endcan
    </div>
</div>

{{-- Indicateurs (raccourcis cliquables) --}}
<div class="row g-2 mb-3">
    @foreach($cartes as $code => [$libelle, $icone, $couleur, $n])
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ $lien(['vue' => $code ?: null]) }}" class="card border-0 shadow-sm h-100 text-decoration-none text-reset @if($vue === $code) border-start border-4 border-{{ $couleur }} @endif">
                <div class="card-body py-2">
                    <div class="small text-muted"><i class="bi bi-{{ $icone }} text-{{ $couleur }} me-1"></i>{{ $libelle }}</div>
                    <div class="fs-5 fw-bold">{{ $n }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    @if($vue !== '')<input type="hidden" name="vue" value="{{ $vue }}">@endif
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-0">Recherche</label>
                <input type="search" name="q" class="form-control form-control-sm" value="{{ $f('q') }}" placeholder="Titre, référence, nom de fichier, description, texte OCR…">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Statut</label>
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach ($statuts as $val => $lib)<option value="{{ $val }}" @selected($f('statut') === $val)>{{ $lib }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Confidentialité</label>
                <select name="niveau_confidentialite" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach ($niveaux as $val => $lib)<option value="{{ $val }}" @selected($f('niveau_confidentialite') === $val)>{{ $lib }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">Tri</label>
                <div class="input-group input-group-sm">
                    <select name="tri" class="form-select form-select-sm">
                        @foreach (GedSearchService::TRIS as $val => $lib)<option value="{{ $val }}" @selected(($f('tri') ?: 'date_depot') === $val)>{{ $lib }}</option>@endforeach
                    </select>
                    <select name="ordre" class="form-select form-select-sm" style="max-width: 70px" title="Ordre">
                        <option value="desc" @selected($f('ordre') !== 'asc')>↓</option>
                        <option value="asc" @selected($f('ordre') === 'asc')>↑</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2 d-flex gap-1">
                <button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filtrer</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#ged-filtres-avances" title="Plus de filtres"><i class="bi bi-sliders"></i></button>
                @if($actifs)<a href="{{ route('ged.documents.index') }}" class="btn btn-sm btn-outline-secondary" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>@endif
            </div>
        </div>
        <div class="collapse @if($avances) show @endif" id="ged-filtres-avances">
            <div class="row g-2 align-items-end mt-1">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Module d'origine</label>
                    <select name="module_source" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        @foreach ($modules as $m)<option value="{{ $m }}" @selected($f('module_source') === $m)>{{ $m }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Catégorie</label>
                    <select name="categorie_id" class="form-select form-select-sm">
                        <option value="">Toutes</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected($f('categorie_id') == $c->id)>{{ $c->libelle }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Type</label>
                    <select name="type_document_id" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        @foreach ($types as $t)<option value="{{ $t->id }}" @selected($f('type_document_id') == $t->id)>{{ $t->libelle }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-0">Format</label>
                    <select name="extension" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        @foreach ($extensions as $e)<option value="{{ $e }}" @selected($f('extension') === $e)>{{ strtoupper($e) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">Déposé du</label><input type="date" name="date_depot_debut" value="{{ $f('date_depot_debut') }}" class="form-control form-control-sm"></div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">au</label><input type="date" name="date_depot_fin" value="{{ $f('date_depot_fin') }}" class="form-control form-control-sm"></div>
            </div>
        </div>
    </div>
</form>

@include('ged.partials._liste-documents', ['documents' => $documents, 'parPage' => true])
@endsection
