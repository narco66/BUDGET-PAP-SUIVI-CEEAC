@extends('layouts.app')
@section('titre', 'Nouveau plan d\'amélioration')
@php $val = fn ($k, $d = null) => old($k, $plan->$k ?? $d); @endphp
@section('contenu')
<h4 class="fw-bold mb-3">Nouveau plan d'amélioration</h4>
<form method="POST" action="{{ route('suivi-evaluation.plans.store') }}">
    @csrf
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="exercice_id" label="Exercice" col="4" :value="$val('exercice_id')" required :placeholder="false">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected($val('exercice_id') == $ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="mission_suivi_id" label="Mission" col="4" :value="$val('mission_suivi_id')" placeholder="—">
                @foreach ($missions as $m)
                    <option value="{{ $m->id }}" @selected($val('mission_suivi_id') == $m->id)>{{ $m->numero }} — {{ Str::limit($m->libelle, 40) }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="statut" label="Statut" col="4" :value="$val('statut')" required :options="$statuts" :placeholder="false" />
            <x-form.input name="intitule" label="Intitulé" col="12" :value="$val('intitule')" required />
            <x-form.textarea name="constat" label="Constat" col="12" :value="$val('constat')" :rows="2" />
            <x-form.select name="structure_id" label="Structure" col="4" :value="$val('structure_id')" placeholder="—">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->sigle ?? $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="date_debut" label="Début" type="date" col="4" :value="$val('date_debut')" />
            <x-form.input name="echeance" label="Échéance" type="date" col="4" :value="$val('echeance')" />
        </div>
        <x-form.footer :cancel-url="route('suivi-evaluation.plans.index')" />
    </div>
</form>
@endsection
