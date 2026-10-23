@extends('layouts.app')
@section('titre', 'Dépôt documentaire')

@section('contenu')
@include('ged._nav-module', ['page' => 'depot'])

<h4 class="fw-bold mb-3">Dépôt documentaire</h4>

<form method="POST" action="{{ route('ged.documents.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.input name="titre" label="Titre" col="8" :value="old('titre')" required />
            <x-form.file name="fichier" label="Fichier" col="4" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt,.csv" help="Max 10 Mo · PDF, images, Office" />
            <x-form.textarea name="description" label="Description" col="12" :value="old('description')" :rows="2" />
            <x-form.select name="categorie_id" label="Catégorie" col="3" :value="old('categorie_id')" placeholder="—">
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(old('categorie_id') == $c->id)>{{ $c->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="type_document_id" label="Type" col="3" :value="old('type_document_id')" placeholder="—">
                @foreach ($types as $t)
                    <option value="{{ $t->id }}" @selected(old('type_document_id') == $t->id)>{{ $t->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="dossier_id" label="Dossier" col="3" :value="old('dossier_id')" placeholder="—">
                @foreach ($dossiers as $d)
                    <option value="{{ $d->id }}" @selected(old('dossier_id') == $d->id)>{{ $d->titre }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="niveau_confidentialite" label="Confidentialité" col="3" :value="old('niveau_confidentialite', 'interne')" :options="$niveaux" :placeholder="false" />
            <x-form.input name="module_source" label="Module source" col="3" :value="old('module_source', 'GED')" placeholder="PAP, BUDGET, ENGAGEMENT…" />
            <x-form.input name="date_document" label="Date du document" type="date" col="3" :value="old('date_document', date('Y-m-d'))" />
            <x-form.input name="date_expiration" label="Date d'expiration" type="date" col="3" :value="old('date_expiration')" />
            <x-form.input name="tags" label="Tags (séparés par virgule)" col="3" :value="old('tags')" />
        </div>
        <x-form.footer :cancel-url="route('ged.documents.index')" submit-label="Déposer" submit-icon="bi-cloud-upload" />
    </div>
</form>
@endsection
