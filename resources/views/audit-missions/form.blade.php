@extends('layouts.app')
@section('titre', ($mission->exists ? 'Modifier' : 'Planifier').' une mission d\'audit')
@php
    $val = fn ($k, $d = null) => old($k, $mission->$k ?? $d);
    $dateVal = fn ($k) => optional($val($k) instanceof \DateTimeInterface ? $val($k) : null)?->format('Y-m-d') ?? $val($k);
@endphp

@section('contenu')
<a href="{{ route('audit-missions.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Missions d'audit</a>
<h4 class="fw-bold mb-3">{{ $mission->exists ? 'Modifier la mission '.$mission->numero : 'Planifier une mission d\'audit' }}</h4>

<form method="POST" action="{{ $mission->exists ? route('audit-missions.update', $mission) : route('audit-missions.store') }}">
    @csrf @if($mission->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.input name="libelle" label="Libellé de la mission" col="8" :value="$val('libelle')" required maxlength="255" />
            <x-form.select name="type" label="Type" col="4" :value="$val('type')" required :options="$types" />
            <x-form.select name="structure_id" label="Entité auditée" col="6" :value="$val('structure_id')" placeholder="— Aucune —">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->code }} · {{ $s->sigle ?: $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="chef_mission_user_id" label="Chef de mission (auditeur)" col="6" :value="$val('chef_mission_user_id')" placeholder="— Aucun —">
                @foreach ($utilisateurs as $u)
                    <option value="{{ $u->id }}" @selected($val('chef_mission_user_id') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="domaine" label="Domaine / processus" col="4" :value="$val('domaine')" maxlength="150" />
            <x-form.select name="exercice_id" label="Exercice" col="3" :value="$val('exercice_id')" placeholder="— Aucun —">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected($val('exercice_id') == $ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="statut" label="Statut" col="5" :value="$val('statut')" required :options="$statuts" />
            <x-form.input name="date_debut" label="Date début" type="date" col="3" :value="$dateVal('date_debut')" />
            <x-form.input name="date_fin" label="Date fin" type="date" col="3" :value="$dateVal('date_fin')" />
            <x-form.input name="objectif" label="Objectif" col="6" :value="$val('objectif')" />
            <x-form.textarea name="perimetre" label="Périmètre" col="12" :value="$val('perimetre')" :rows="2" />
        </div>
        <x-form.footer :cancel-url="route('audit-missions.index')" />
    </div>
</form>
@endsection
