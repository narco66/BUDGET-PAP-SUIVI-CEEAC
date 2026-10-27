@extends('layouts.app')
@section('titre', 'Nouvelle importation')

@section('contenu')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-cloud-upload me-1"></i>Nouvelle importation</h1>
    <a href="{{ route('imports.index') }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="card border-0 shadow-sm">@csrf
            <div class="card-body">
                <h2 class="h6 fw-bold">1. Domaine des données</h2>
                <div class="list-group mb-3">
                    @forelse ($domaines as $code => $d)
                        <label class="list-group-item d-flex gap-2">
                            <input class="form-check-input mt-1" type="radio" name="domaine" value="{{ $code }}" @checked(old('domaine', $domaineChoisi) === $code) required data-exercice="{{ $d->exigeExercice() ? 1 : 0 }}">
                            <span class="flex-grow-1">
                                <strong>{{ $d->libelle() }}</strong>
                                @if ($d->circuit())<span class="badge bg-warning-subtle text-warning-emphasis border ms-1">approbation requise</span>@endif
                                <span class="d-block small text-muted">{{ $d->description() }}</span>
                                <span class="d-block small">Colonnes : @foreach ($d->champs() as $c)<code class="{{ $c->requis ? 'fw-bold' : '' }}">{{ $c->champ }}</code>@if (! $loop->last), @endif @endforeach
                                    · <a href="{{ route('imports.modele', $code) }}"><i class="bi bi-download me-1"></i>Télécharger le modèle</a></span>
                            </span>
                        </label>
                    @empty
                        <div class="list-group-item text-muted">Vous ne disposez du droit métier d'import sur aucun domaine.</div>
                    @endforelse
                </div>

                <h2 class="h6 fw-bold">2. Mode et périmètre</h2>
                <div class="row g-2 mb-3">
                    <div class="col-md-7"><label class="form-label small mb-0">Mode</label>
                        <select name="mode" class="form-select form-select-sm">
                            @foreach (\App\Models\ImportLot::MODES as $code => $libelle)<option value="{{ $code }}" @selected(old('mode', 'insertion') === $code)>{{ $libelle }}</option>@endforeach
                        </select>
                        <div class="form-text">Insertion : seules les nouvelles entités sont créées. Mise à jour contrôlée : en plus, les champs autorisés des entités existantes sont modifiés. Simulation : diagnostic seul.</div>
                    </div>
                    <div class="col-md-5"><label class="form-label small mb-0">Exercice <span class="text-muted">(budget annuel)</span></label>
                        <select name="exercice_id" class="form-select form-select-sm"><option value="">—</option>
                            @foreach ($exercices as $ex)<option value="{{ $ex->id }}" @selected((string) old('exercice_id') === (string) $ex->id)>{{ $ex->annee }} ({{ \App\Models\Exercice::STATUTS[$ex->statut] ?? $ex->statut }})</option>@endforeach
                        </select></div>
                </div>

                <h2 class="h6 fw-bold">3. Fichier</h2>
                <div class="row g-2 mb-2">
                    <div class="col-md-7"><input type="file" name="fichier" class="form-control form-control-sm @error('fichier') is-invalid @enderror" required accept=".xlsx,.xls,.csv,.json">
                        <div class="form-text">Excel, CSV ou JSON · {{ config('import.taille_max_mo') }} Mo et {{ number_format(config('import.lignes_max'), 0, ',', ' ') }} lignes au maximum · stockage privé, empreinte SHA-256.</div>
                        @error('fichier')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-5"><input type="text" name="feuille" value="{{ old('feuille') }}" class="form-control form-control-sm" placeholder="Feuille Excel (facultatif : la première)"></div>
                </div>
                @if ($modeles->isNotEmpty())
                <div class="mb-2"><label class="form-label small mb-0">Modèle de correspondance enregistré (facultatif)</label>
                    <select name="modele_id" class="form-select form-select-sm"><option value="">Détection automatique</option>
                        @foreach ($modeles as $domaine => $liste)<optgroup label="{{ $domaines[$domaine]?->libelle() ?? $domaine }}">@foreach ($liste as $m)<option value="{{ $m->id }}">{{ $m->nom }}</option>@endforeach</optgroup>@endforeach
                    </select></div>
                @endif
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('imports.index') }}" class="btn btn-sm btn-outline-secondary">Annuler</a>
                <button class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Analyser</button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body small">
            <h2 class="h6 fw-bold"><i class="bi bi-shield-check me-1"></i>Garanties</h2>
            <ul class="mb-0 ps-3">
                <li>L'analyse et la simulation n'écrivent aucune donnée métier.</li>
                <li>Les référentiels existants sont reconnus et protégés : identifiants conservés, aucun effacement, aucune suppression.</li>
                <li>Un crédit ouvert existant n'est jamais modifié par import (révision budgétaire).</li>
                <li>Les lots sensibles sont approuvés par un autre agent habilité (tâche dans « Mes Tâches »).</li>
                <li>L'exécution est transactionnelle : tout le lot, ou rien.</li>
            </ul>
        </div></div>
        <div class="card border-0 shadow-sm"><div class="card-body small">
            <h2 class="h6 fw-bold">Domaines prévus</h2>
            @foreach ($planifies as [$priorite, $libelle, $note])
                <div class="mb-1"><span class="badge bg-light text-dark border">{{ $priorite }}</span> {{ $libelle }}<div class="text-muted">{{ $note }}</div></div>
            @endforeach
        </div></div>
    </div>
</div>
@endsection
