@extends('layouts.app')
@section('titre', $connaissance->exists ? 'Modifier contenu' : 'Nouveau contenu')
@php
    $val = fn ($k, $d = null) => old($k, $connaissance->$k ?? $d);
    $tags = old('tags', is_array($connaissance->tags ?? null) ? implode(', ', $connaissance->tags) : '');
@endphp

@section('contenu')
<a href="{{ route('ged.connaissances.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Base de connaissances</a>
<h4 class="fw-bold mb-3">{{ $connaissance->exists ? 'Modifier '.$connaissance->reference : 'Nouveau contenu' }}</h4>

<form method="POST" action="{{ $connaissance->exists ? route('ged.connaissances.update', $connaissance) : route('ged.connaissances.store') }}">
    @csrf @if($connaissance->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.input name="titre" label="Titre" col="8" :value="$val('titre')" required />
            <x-form.select name="type" label="Type" col="4" :value="$val('type', 'wiki')" required :options="$types" :placeholder="false" />
            <x-form.select name="module" label="Module métier" col="4" :value="$val('module', 'general')" required :options="$modules" :placeholder="false" />
            <x-form.input name="categorie" label="Catégorie (optionnel)" col="4" :value="$val('categorie')" placeholder="Ex. Onboarding, Clôture…" />
            <x-form.input name="tags" label="Tags (séparés par virgule)" col="4" :value="$tags" placeholder="budget, xaf, validation" />
            <x-form.textarea name="resume" label="Résumé" col="12" :value="$val('resume')" :rows="2" />
            <x-form.textarea name="contenu" label="Contenu (Markdown)" col="12" :value="$val('contenu')" :rows="14" required />
            <x-form.select name="ged_document_id" label="Document GED lié (optionnel)" col="6" :value="$val('ged_document_id')" placeholder="—">
                @foreach ($documents as $doc)
                    <option value="{{ $doc->id }}" @selected($val('ged_document_id') == $doc->id)>{{ $doc->reference ?? $doc->id }} — {{ \Illuminate\Support\Str::limit($doc->titre, 40) }}</option>
                @endforeach
            </x-form.select>
        </div>
        <x-form.footer :cancel-url="route('ged.connaissances.index')" />
    </div>
</form>
@endsection
