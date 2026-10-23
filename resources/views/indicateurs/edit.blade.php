@extends('layouts.app')
@section('titre', 'Modifier · '.$indicateur->code)

@section('contenu')
@php
    $niveau = collect(\App\Rbm\Niveaux::tout())->search(fn ($cfg) => $cfg['model'] === $indicateur->rbm_type);
    $noeud = $indicateur->rbm;
    $config = $niveau ? \App\Rbm\Niveaux::get($niveau) : null;
    $retour = $niveau ? route('rbm.indicateurs.index', [$niveau, $indicateur->rbm_id]).($exerciceId ? '?exercice='.$exerciceId : '') : route('rbm.home');
@endphp

<div class="mb-3">
    @if($config)<span class="badge bg-secondary">{{ strtoupper($config['label']) }}</span>@endif
    @if($noeud)<span class="rbm-code">{{ $noeud->code }}</span> <strong>{{ $noeud->intitule }}</strong>@endif
    <a href="{{ $retour }}" class="btn btn-sm btn-light float-end"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-pencil me-1"></i>Modifier l'indicateur <code>{{ $indicateur->code }}</code></div>
            <div class="card-body">
                <form action="{{ route('rbm.indicateurs.update', $indicateur) }}?exercice={{ $exerciceId }}" method="POST">
                    @csrf @method('PUT')
                    <div class="row g-2">
                        <x-form.input name="code" label="Code" col="4" label-class="small" input-class="form-control-sm"
                            :value="$indicateur->code" required />
                        <x-form.input name="libelle" label="Libellé" col="8" label-class="small" input-class="form-control-sm"
                            :value="$indicateur->libelle" required />
                        <div class="col-md-6">
                            <x-form.select name="type_indicateur_id" label="Type catalogue" :value="$indicateur->type_indicateur_id" required label-class="small" select-class="form-select-sm">
                                @foreach($typesIndicateurs as $t)
                                    <option value="{{ $t->id }}" @selected(old('type_indicateur_id', $indicateur->type_indicateur_id) == $t->id)>{{ $t->code }} — {{ $t->libelle }}</option>
                                @endforeach
                            </x-form.select>
                            @if($indicateur->type)<p class="form-text small mb-0">Legacy : {{ $indicateur->type }}</p>@endif
                        </div>
                        <x-form.select name="periodicite" label="Périodicité" col="6" label-class="small" select-class="form-select-sm"
                            :value="$indicateur->periodicite" :placeholder="false"
                            :options="['mensuel'=>'mensuel','trimestriel'=>'trimestriel','semestriel'=>'semestriel','annuel'=>'annuel','pluriannuel'=>'pluriannuel']" />
                        <div class="col-md-6">
                            <x-form.select name="unite_mesure_id" label="Unité catalogue" :value="$indicateur->unite_mesure_id" required label-class="small" select-class="form-select-sm">
                                @foreach($unitesMesure as $u)
                                    <option value="{{ $u->id }}" @selected(old('unite_mesure_id', $indicateur->unite_mesure_id) == $u->id)>{{ $u->code }} ({{ $u->symbole }})</option>
                                @endforeach
                            </x-form.select>
                            @if($indicateur->unite)<p class="form-text small mb-0">Legacy : {{ $indicateur->unite }}</p>@endif
                        </div>
                        <x-form.input name="valeur_reference" label="Valeur référence" type="number" step="any" col="4" label-class="small" input-class="form-control-sm" :value="$indicateur->valeur_reference" />
                        <x-form.input name="valeur_cible" label="Valeur cible" type="number" step="any" col="4" label-class="small" input-class="form-control-sm" :value="$indicateur->valeur_cible" />
                        <x-form.input name="seuil_alerte" label="Seuil alerte (%)" type="number" step="any" col="4" label-class="small" input-class="form-control-sm" :value="$indicateur->seuil_alerte" />
                        <x-form.input name="source_verification" label="Source (texte libre)" col="12" label-class="small" input-class="form-control-sm" :value="$indicateur->source_verification" />
                        <div class="col-12">
                            <label class="form-label small">Sources référentiel</label>
                            <select name="sources_verification[]" class="form-select form-select-sm @error('sources_verification') is-invalid @enderror @error('sources_verification.*') is-invalid @enderror" multiple size="3">
                                @php $selected = old('sources_verification', $indicateur->sourcesVerification->pluck('id')->all()); @endphp
                                @foreach($sourcesVerification as $s)
                                    <option value="{{ $s->id }}" @selected(in_array($s->id, $selected))>{{ $s->code }} — {{ $s->libelle }}</option>
                                @endforeach
                            </select>
                            @error('sources_verification')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('sources_verification.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <x-form.textarea name="methode_calcul" label="Méthode de calcul" col="12" label-class="small" input-class="form-control-sm" :value="$indicateur->methode_calcul" :rows="3" />
                    </div>
                    <x-form.actions :cancel-url="$retour" submit-label="Enregistrer" class="mt-3" />
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
