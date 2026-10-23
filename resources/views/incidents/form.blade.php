@extends('layouts.app')
@section('titre', ($incident->exists ? 'Modifier' : 'Déclarer').' un incident')
@php $val = fn ($k, $d = null) => old($k, $incident->$k ?? $d); @endphp

@section('contenu')
<a href="{{ route('incidents.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Incidents</a>
<h4 class="fw-bold mb-3">{{ $incident->exists ? 'Modifier '.$incident->numero : 'Déclarer un incident' }}</h4>

<form method="POST" action="{{ $incident->exists ? route('incidents.update', $incident) : route('incidents.store') }}">
    @csrf @if($incident->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.input name="titre" label="Titre de l'incident" col="8" :value="$val('titre')" required maxlength="255" />
            <x-form.select name="gravite" label="Gravité" col="4" :value="$val('gravite')" required :options="$gravites" />
            <x-form.select name="incident_categorie_id" label="Catégorie" col="4" :value="$val('incident_categorie_id')" placeholder="— Aucune —">
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected($val('incident_categorie_id') == $c->id)>{{ $c->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="source" label="Source" col="4" :value="$val('source')" required :options="$sources" />
            <x-form.select name="structure_id" label="Structure concernée" col="4" :value="$val('structure_id')" placeholder="— Aucune —">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->code }} · {{ $s->sigle ?: $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="assigne_a" label="Assigné à" col="6" :value="$val('assigne_a')" placeholder="— Non assigné —">
                @foreach ($utilisateurs as $u)
                    <option value="{{ $u->id }}" @selected($val('assigne_a') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.textarea name="description" label="Description" col="12" :value="$val('description')" :rows="3" />
            <x-form.textarea name="impact" label="Impact" col="12" :value="$val('impact')" :rows="2" />
        </div>
        <x-form.footer :cancel-url="route('incidents.index')" />
    </div>
</form>
@endsection
