@extends('layouts.app')
@section('titre', ($controle->exists ? 'Modifier' : 'Nouveau').' contrôle interne')
@php $val = fn ($k, $d = null) => old($k, $controle->$k ?? $d); @endphp

@section('contenu')
<a href="{{ route('controle-interne.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Contrôle interne</a>
<h4 class="fw-bold mb-3">{{ $controle->exists ? 'Modifier le contrôle '.$controle->code : 'Nouveau contrôle interne' }}</h4>

<form method="POST" action="{{ $controle->exists ? route('controle-interne.update', $controle) : route('controle-interne.store') }}">
    @csrf @if($controle->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="composante" label="Composante COSO" col="6" :value="$val('composante')" required :options="$composantes" :placeholder="false" />
            <x-form.select name="type" label="Type" col="3" :value="$val('type')" required :options="$types" :placeholder="false" />
            <x-form.select name="frequence" label="Fréquence" col="3" :value="$val('frequence')" required :options="$frequences" :placeholder="false" />
            <x-form.input name="libelle" label="Libellé du contrôle" col="12" :value="$val('libelle')" required maxlength="255" />
            <x-form.textarea name="description" label="Description" col="12" :value="$val('description')" :rows="2" />
            <x-form.input name="processus" label="Processus / domaine" col="4" :value="$val('processus')" maxlength="150" />
            <x-form.select name="structure_id" label="Structure" col="4" :value="$val('structure_id')" placeholder="— Aucune —">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->code }} · {{ $s->sigle ?: $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="responsable_user_id" label="Responsable" col="4" :value="$val('responsable_user_id')" placeholder="— Aucun —">
                @foreach ($utilisateurs as $u)
                    <option value="{{ $u->id }}" @selected($val('responsable_user_id') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="evaluation" label="Évaluation d'efficacité" col="4" :value="$val('evaluation', 'non_evalue')" required :options="$evaluations" :placeholder="false" />
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input type="hidden" name="actif" value="0">
                    <input class="form-check-input" type="checkbox" name="actif" value="1" id="actif" @checked($val('actif', true))>
                    <label class="form-check-label" for="actif">Contrôle actif</label>
                </div>
            </div>
        </div>
        <x-form.footer :cancel-url="route('controle-interne.index')" />
    </div>
</form>
@endsection
