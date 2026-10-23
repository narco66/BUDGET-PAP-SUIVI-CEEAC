@extends('layouts.app')
@section('titre', 'Modifier — '.$document->titre)

@section('contenu')
<h4 class="fw-bold mb-3">Modifier le document</h4>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('ged.documents.update', $document) }}">
            @csrf @method('PUT')
            <div class="row g-3">
                <x-form.input name="titre" label="Titre" col="8" :value="$document->titre" required />
                <x-form.textarea name="description" label="Description" col="12" :value="$document->description" :rows="2" />
                <x-form.select name="categorie_id" label="Catégorie" col="3" :value="$document->categorie_id" placeholder="—">
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('categorie_id', $document->categorie_id) == $c->id)>{{ $c->libelle }}</option>
                    @endforeach
                </x-form.select>
                <x-form.select name="type_document_id" label="Type" col="3" :value="$document->type_document_id" placeholder="—">
                    @foreach ($types as $t)
                        <option value="{{ $t->id }}" @selected(old('type_document_id', $document->type_document_id) == $t->id)>{{ $t->libelle }}</option>
                    @endforeach
                </x-form.select>
                <x-form.select name="niveau_confidentialite" label="Confidentialité" col="3" :value="$document->niveau_confidentialite?->value" :placeholder="false" :options="$niveaux" />
                <x-form.input name="tags" label="Tags" col="3" :value="old('tags', $document->tags->pluck('nom')->implode(', '))" />
            </div>
            <x-form.actions :cancel-url="route('ged.documents.show', $document)" class="mt-4" />
        </form>
    </div>
</div>
@endsection
