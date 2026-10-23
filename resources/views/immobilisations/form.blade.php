@extends('layouts.app')
@section('titre', $immobilisation->exists ? 'Modifier immobilisation' : 'Nouvelle immobilisation')
@php
    $val = fn ($k, $d = null) => old($k, $immobilisation->$k ?? $d);
    $date = fn ($k) => ($v = old($k, $immobilisation->$k ?? null)) instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
@endphp

@section('contenu')
<a href="{{ route('immobilisations.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Immobilisations</a>
<h4 class="fw-bold mb-3">{{ $immobilisation->exists ? 'Modifier '.$immobilisation->numero : 'Nouvelle immobilisation' }}</h4>

<form method="POST" action="{{ $immobilisation->exists ? route('immobilisations.update', $immobilisation) : route('immobilisations.store') }}">
    @csrf @if($immobilisation->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="exercice_id" label="Exercice" col="3" :value="$val('exercice_id')" required :placeholder="false">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected($val('exercice_id') == $ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="numero" label="N° (auto si vide)" col="3" :value="$val('numero')" placeholder="IMM-2026-001" />
            <x-form.select name="categorie" label="Catégorie" col="3" :value="$val('categorie')" required :options="$categories" :placeholder="false" />
            <x-form.select name="statut" label="Statut" col="3" :value="$val('statut')" required :options="$statuts" :placeholder="false" />
            <x-form.input name="designation" label="Désignation" col="8" :value="$val('designation')" required />
            <x-form.input name="marque" label="Marque / modèle" col="4" :value="$val('marque')" />
            <x-form.input name="numero_serie" label="N° série / immatriculation" col="4" :value="$val('numero_serie')" />
            <x-form.input name="date_acquisition" label="Date d'acquisition" type="date" col="4" :value="$date('date_acquisition')" required />
            <x-form.input name="valeur_acquisition" label="Valeur d'acquisition (XAF)" type="number" col="4" step="0.01" min="0" :value="$val('valeur_acquisition', 0)" required />
            <x-form.input name="duree_amortissement_ans" label="Durée amortissement (ans)" type="number" col="4" min="1" max="50" :value="$val('duree_amortissement_ans', 5)" required />
            <x-form.input name="compte_syscohada" label="Compte SYSCOHADA" col="4" :value="$val('compte_syscohada')" placeholder="244000" />
            <x-form.input name="localisation" label="Localisation" col="6" :value="$val('localisation')" />
            <x-form.input name="affectation" label="Affectation / utilisateur" col="6" :value="$val('affectation')" />
            <x-form.select name="structure_id" label="Structure de rattachement" col="4" :value="$val('structure_id')" placeholder="—">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->sigle ?? $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="engagement_id" label="Engagement d'acquisition" col="8" :value="$val('engagement_id')" placeholder="—">
                @foreach ($engagements as $e)
                    <option value="{{ $e->id }}" @selected($val('engagement_id') == $e->id)>{{ $e->numero }} — {{ \Illuminate\Support\Str::limit($e->objet, 30) }}</option>
                @endforeach
            </x-form.select>
            <x-form.textarea name="observations" label="Observations" col="12" :value="$val('observations')" :rows="2" />
        </div>
        <x-form.footer :cancel-url="route('immobilisations.index')" />
    </div>
</form>
@endsection
