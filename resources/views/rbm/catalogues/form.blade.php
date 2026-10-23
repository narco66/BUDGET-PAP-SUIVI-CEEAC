@extends('layouts.app')
@section('titre', ($item ? 'Modifier' : 'Créer').' — '.$label)

@section('contenu')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ $item ? route('rbm.catalogues.update', [$catalogue, $item->id]) : route('rbm.catalogues.store', $catalogue) }}">
                    @csrf
                    @if($item) @method('PUT') @endif
                    <div class="row g-3">
                        <x-form.input name="code" label="Code" col="4" :value="old('code', $item->code ?? '')" required maxlength="40" />
                        <x-form.input name="libelle" label="Libellé" col="8" :value="old('libelle', $item->libelle ?? '')" required />
                        @if($catalogue === 'unites-mesure')
                            <x-form.input name="symbole" label="Symbole" col="4" :value="old('symbole', $item->symbole ?? '')" maxlength="20" />
                        @endif
                        @if($catalogue === 'sources-verification')
                            <x-form.input name="url" label="URL documentaire" type="url" col="12" :value="old('url', $item->url ?? '')" />
                        @endif
                        <x-form.textarea name="description" label="Description" col="12" :value="old('description', $item->description ?? '')" :rows="3" />
                        <x-form.input name="ordre" label="Ordre" type="number" col="4" min="0" :value="old('ordre', $item->ordre ?? 0)" />
                        <x-form.select name="statut" label="Statut" col="4" :value="old('statut', $item->statut ?? 'actif')" :placeholder="false">
                            @foreach(['actif','inactif','archive'] as $s)
                                <option value="{{ $s }}" @selected(old('statut', $item->statut ?? 'actif')===$s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </x-form.select>
                    </div>
                    <x-form.actions :cancel-url="route('rbm.catalogues.index', $catalogue)" cancel-label="Annuler" class="mt-4" />
                </form>
            </div>
        </div>
    </div>
</div>
@if($catalogue === 'sources-verification' && $item)
    @include('partials._ged_documents', ['attachable' => $item, 'type' => 'source_verification'])
@endif
@endsection
