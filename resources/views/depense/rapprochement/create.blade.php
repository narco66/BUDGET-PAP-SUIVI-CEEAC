@extends('layouts.app')
@section('titre', 'Nouveau rapprochement')
@section('contenu')
<div class="card border-0 shadow-sm" style="max-width:640px;"><div class="card-body">
    <h5 class="fw-bold mb-3">Nouvelle session de rapprochement</h5>
    <form method="POST" action="{{ route('depense.rapprochement.store') }}">@csrf
        <div class="row g-3">
            <x-form.select name="exercice_id" label="Exercice" col="4" required :placeholder="false">
                @foreach($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected(old('exercice_id')==$ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="intitule" label="Intitulé" col="8" :value="old('intitule')" required placeholder="Relevé janvier 2026" />
            <x-form.input name="compte_bancaire" label="Compte bancaire" col="6" :value="old('compte_bancaire')" />
            <x-form.input name="date_debut" label="Du" type="date" col="3" :value="old('date_debut')" />
            <x-form.input name="date_fin" label="Au" type="date" col="3" :value="old('date_fin')" />
        </div>
        <x-form.actions :cancel-url="route('depense.rapprochement.index')" submit-label="Créer" />
    </form>
</div></div>
@endsection
