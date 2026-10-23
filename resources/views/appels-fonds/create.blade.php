@extends('layouts.app')
@section('titre', 'Nouvel appel de fonds')

@section('contenu')
<div class="mb-3"><a href="{{ route('appels-fonds.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>

<form method="POST" action="{{ route('appels-fonds.store') }}">
    @csrf
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="exercice_id" label="Exercice" col="4" :value="old('exercice_id')" required placeholder="—">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected(old('exercice_id')==$ex->id)>Exercice {{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="reference" label="Référence" col="4" :value="old('reference')" maxlength="60" required />
            <x-form.input name="montant_appele" label="Montant appelé (XAF)" type="number" col="4" step="0.01" min="0.01" :value="old('montant_appele')" required />
            <x-form.input name="objet" label="Objet" col="12" :value="old('objet')" maxlength="255" placeholder="Ex. Contribution statutaire 2026" required />
            <x-form.select name="etat_membre_id" label="État membre débiteur" col="6" :value="old('etat_membre_id')" placeholder="— (PTF / autre)">
                @foreach ($etats as $etat)
                    <option value="{{ $etat->id }}" @selected(old('etat_membre_id')==$etat->id)>{{ $etat->pays }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="source_financement_id" label="Source / PTF" col="6" :value="old('source_financement_id')" placeholder="—">
                @foreach ($sources as $src)
                    <option value="{{ $src->id }}" @selected(old('source_financement_id')==$src->id)>{{ $src->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="date_emission" label="Date d'émission" type="date" col="4" :value="old('date_emission', $appel->date_emission)" required />
            <x-form.input name="date_echeance" label="Échéance" type="date" col="4" :value="old('date_echeance')" />
            <x-form.input name="taux_penalite" label="Taux de pénalité (% sur arriéré)" type="number" col="4" step="0.01" min="0" max="100" :value="old('taux_penalite', 0)" />
            <x-form.textarea name="observations" label="Observations" col="12" :value="old('observations')" :rows="2" />
        </div>
        <x-form.footer :cancel-url="route('appels-fonds.index')" submit-label="Émettre l'appel" />
    </div>
</form>
@endsection
