@extends('layouts.app')
@section('titre', 'Nouvelle campagne de suivi')
@section('contenu')
<div class="card border-0 shadow-sm" style="max-width:720px;"><div class="card-body">
    <form action="{{ route('suivi.store') }}" method="POST">@csrf
        <div class="row g-3">
            <x-form.select name="exercice_id" label="Exercice" col="3" required :placeholder="false">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected(old('exercice_id', $ex->statut==='ouvert' ? $ex->id : null)==$ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="type" label="Type" col="3" required
                :options="['mensuel'=>'Mensuel','trimestriel'=>'Trimestriel','semestriel'=>'Semestriel','annuel'=>'Annuel','pluriannuel'=>'Pluriannuel']" />
            <x-form.input name="periode" label="Période" col="3" :value="old('periode')" placeholder="2026-T1" required />
            <x-form.input name="libelle" label="Libellé" col="6" :value="old('libelle')" required />
            <x-form.input name="date_debut" label="Début" type="date" col="3" :value="old('date_debut')" />
            <x-form.input name="date_fin" label="Fin" type="date" col="3" :value="old('date_fin')" />
        </div>
        <x-form.actions :cancel-url="route('suivi.index')" submit-label="Créer" />
    </form>
</div></div>
@endsection
