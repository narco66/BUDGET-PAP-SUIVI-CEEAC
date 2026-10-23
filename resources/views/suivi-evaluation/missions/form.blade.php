@extends('layouts.app')
@section('titre', 'Nouvelle mission S&E')
@php $val = fn ($k, $d = null) => old($k, $mission->$k ?? $d); @endphp
@section('contenu')
<h4 class="fw-bold mb-3">Nouvelle mission</h4>
<form method="POST" action="{{ route('suivi-evaluation.missions.store') }}">
    @csrf
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="exercice_id" label="Exercice" col="3" :value="$val('exercice_id')" required :placeholder="false">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected($val('exercice_id') == $ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="type" label="Type" col="3" :value="$val('type')" required :options="$types" :placeholder="false" />
            <x-form.select name="statut" label="Statut" col="3" :value="$val('statut')" required :options="$statuts" :placeholder="false" />
            <x-form.input name="score_mission" label="Score (%)" type="number" col="3" step="0.01" :value="$val('score_mission')" />
            <x-form.input name="libelle" label="Libellé" col="12" :value="$val('libelle')" required />
            <x-form.textarea name="objectif" label="Objectif" col="12" :value="$val('objectif')" :rows="2" />
            <x-form.select name="structure_id" label="Structure" col="4" :value="$val('structure_id')" placeholder="—">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->sigle ?? $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="responsable_user_id" label="Responsable" col="4" :value="$val('responsable_user_id')" placeholder="—">
                @foreach ($responsables as $u)
                    <option value="{{ $u->id }}" @selected($val('responsable_user_id') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="date_debut" label="Début" type="date" col="2" :value="$val('date_debut')" />
            <x-form.input name="date_fin" label="Fin" type="date" col="2" :value="$val('date_fin')" />
        </div>
        <x-form.footer :cancel-url="route('suivi-evaluation.missions.index')" />
    </div>
</form>
@endsection
