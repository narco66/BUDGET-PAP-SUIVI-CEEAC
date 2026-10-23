@extends('layouts.app')
@section('titre', $recommandation->exists ? 'Modifier la recommandation' : 'Nouvelle recommandation')
@php
    $val = fn ($k, $d = null) => old($k, $recommandation->$k ?? $d);
    $date = fn ($k) => ($v = old($k, $recommandation->$k ?? null)) instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
@endphp

@section('contenu')
<a href="{{ route('recommandations.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Recommandations</a>
<h4 class="fw-bold mb-3">{{ $recommandation->exists ? 'Modifier '.$recommandation->numero : 'Nouvelle recommandation' }}</h4>

<form method="POST" action="{{ $recommandation->exists ? route('recommandations.update', $recommandation) : route('recommandations.store') }}">
    @csrf @if($recommandation->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="exercice_id" label="Exercice" col="3" :value="$val('exercice_id')" required :placeholder="false">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected($val('exercice_id') == $ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="numero" label="N° (auto si vide)" col="3" :value="$val('numero')" placeholder="REC-2026-001" />
            <x-form.select name="categorie" label="Catégorie" col="3" :value="$val('categorie', 'autre')" :options="$categories" :placeholder="false" />
            <x-form.select name="source" label="Source" col="3" :value="$val('source')" required :options="$sources" :placeholder="false" />
            <x-form.input name="libelle" label="Libellé de la recommandation" col="12" :value="$val('libelle')" required />
            <x-form.textarea name="description" label="Description" col="12" :value="$val('description')" :rows="2" />
            <x-form.input name="reference_reunion" label="Référence réunion / session" col="4" :value="$val('reference_reunion')" placeholder="Ex. 24ᵉ session ordinaire" />
            <x-form.input name="date_emission" label="Date d'émission" type="date" col="4" :value="$date('date_emission')" />
            <x-form.input name="echeance" label="Échéance" type="date" col="4" :value="$date('echeance')" />
            <x-form.select name="priorite" label="Priorité" col="3" :value="$val('priorite')" required :options="$priorites" :placeholder="false" />
            <x-form.select name="statut" label="Statut" col="3" :value="$val('statut')" required :options="$statuts" :placeholder="false" />
            <x-form.input name="taux_execution" label="Taux d'exécution (%)" type="number" col="3" step="0.01" min="0" max="100" :value="$val('taux_execution', 0)" required />
            <x-form.select name="structure_id" label="Structure responsable" col="3" :value="$val('structure_id')" placeholder="—">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->sigle ?? $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="responsable_user_id" label="Responsable du suivi" col="6" :value="$val('responsable_user_id')" placeholder="—">
                @foreach ($responsables as $u)
                    <option value="{{ $u->id }}" @selected($val('responsable_user_id') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.textarea name="observations" label="Observations" col="12" :value="$val('observations')" :rows="2" />
        </div>
        <x-form.footer :cancel-url="route('recommandations.index')" />
    </div>
</form>
@endsection
