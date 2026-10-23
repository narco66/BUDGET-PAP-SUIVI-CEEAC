@extends('layouts.app')
@section('titre', $formation->exists ? 'Modifier la session' : 'Nouvelle session')
@php
    $val = fn ($k, $d = null) => old($k, $formation->$k ?? $d);
    $date = fn ($k) => ($v = old($k, $formation->$k ?? null)) instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
@endphp

@section('contenu')
<a href="{{ route('formations.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Formations</a>
<h4 class="fw-bold mb-3">{{ $formation->exists ? 'Modifier '.$formation->numero : 'Nouvelle formation / atelier' }}</h4>

<form method="POST" action="{{ $formation->exists ? route('formations.update', $formation) : route('formations.store') }}">
    @csrf @if($formation->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="exercice_id" label="Exercice" col="3" :value="$val('exercice_id')" required :placeholder="false">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected($val('exercice_id') == $ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="numero" label="N° (auto si vide)" col="3" :value="$val('numero')" placeholder="FOR-2026-001" />
            <x-form.input name="intitule" label="Intitulé" col="6" :value="$val('intitule')" required />
            <x-form.select name="type" label="Type" col="3" :value="$val('type')" required :options="$types" :placeholder="false" />
            <x-form.input name="thematique" label="Thématique" col="5" :value="$val('thematique')" />
            <x-form.input name="lieu" label="Lieu" col="4" :value="$val('lieu')" />
            <x-form.input name="date_debut" label="Date début" type="date" col="3" :value="$date('date_debut')" />
            <x-form.input name="date_fin" label="Date fin" type="date" col="3" :value="$date('date_fin')" />
            <x-form.input name="nb_participants" label="Participants" type="number" col="2" min="0" :value="$val('nb_participants', 0)" required />
            <x-form.input name="nb_hommes" label="Hommes" type="number" col="2" min="0" :value="$val('nb_hommes', 0)" required />
            <x-form.input name="nb_femmes" label="Femmes" type="number" col="2" min="0" :value="$val('nb_femmes', 0)" required />
            <x-form.input name="cout" label="Coût (XAF)" type="number" col="3" step="0.01" min="0" :value="$val('cout', 0)" required />
            <x-form.input name="niveau_satisfaction" label="Satisfaction (%)" type="number" col="3" step="0.01" min="0" max="100" :value="$val('niveau_satisfaction')" />
            <x-form.select name="statut" label="Statut" col="3" :value="$val('statut')" required :options="$statuts" :placeholder="false" />
            <x-form.select name="structure_id" label="Structure organisatrice" col="3" :value="$val('structure_id')" placeholder="—">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->sigle ?? $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="source_financement_id" label="Source de financement" col="4" :value="$val('source_financement_id')" placeholder="—">
                @foreach ($sources as $s)
                    <option value="{{ $s->id }}" @selected($val('source_financement_id') == $s->id)>{{ $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="engagement_id" label="Engagement lié (coût)" col="5" :value="$val('engagement_id')" placeholder="—">
                @foreach ($engagements as $e)
                    <option value="{{ $e->id }}" @selected($val('engagement_id') == $e->id)>{{ $e->numero }} — {{ \Illuminate\Support\Str::limit($e->objet, 30) }}</option>
                @endforeach
            </x-form.select>
            <x-form.textarea name="impact" label="Impact / résultats" col="12" :value="$val('impact')" :rows="2" />
            <x-form.textarea name="observations" label="Observations" col="12" :value="$val('observations')" :rows="2" />
        </div>
        <x-form.footer :cancel-url="route('formations.index')" />
    </div>
</form>
@endsection
