@extends('layouts.app')
@section('titre', ($risque->exists ? 'Modifier' : 'Nouveau').' risque')
@php $val = fn ($k, $d = null) => old($k, $risque->$k ?? $d); @endphp

@section('contenu')
<a href="{{ route('risques.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Registre des risques</a>
<h4 class="fw-bold mb-3">{{ $risque->exists ? 'Modifier le risque '.$risque->code : 'Nouveau risque' }}</h4>

<form method="POST" action="{{ $risque->exists ? route('risques.update', $risque) : route('risques.store') }}">
    @csrf @if($risque->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.input name="code" label="Code" col="3" :value="$val('code')" required input-class="font-monospace" />
            <x-form.select name="categorie_risque_id" label="Catégorie" col="5" :value="$val('categorie_risque_id')" placeholder="— Non catégorisé —">
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected($val('categorie_risque_id') == $c->id)>{{ $c->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="statut" label="Statut" col="4" :value="$val('statut')" required :options="$statuts" />
            <x-form.input name="libelle" label="Libellé" col="12" :value="$val('libelle')" required maxlength="255" />
            <x-form.textarea name="description" label="Description" col="12" :value="$val('description')" :rows="2" />
            <x-form.select name="probabilite" label="Probabilité" col="4" :value="$val('probabilite', 1)" required :placeholder="false">
                @foreach ($probabilites as $k => $v)
                    <option value="{{ $k }}" @selected((int) $val('probabilite', 1) === $k)>{{ $k }} — {{ $v }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="impact" label="Impact" col="4" :value="$val('impact', 1)" required :placeholder="false">
                @foreach ($impacts as $k => $v)
                    <option value="{{ $k }}" @selected((int) $val('impact', 1) === $k)>{{ $k }} — {{ $v }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="processus" label="Processus / domaine" col="4" :value="$val('processus')" maxlength="120" placeholder="Ex. Chaîne de la dépense" />
            <x-form.select name="proprietaire_user_id" label="Propriétaire du risque" col="6" :value="$val('proprietaire_user_id')" placeholder="— Aucun —">
                @foreach ($utilisateurs as $u)
                    <option value="{{ $u->id }}" @selected($val('proprietaire_user_id') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="structure_id" label="Structure" col="3" :value="$val('structure_id')" placeholder="— Aucune —">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->code }} · {{ $s->sigle ?: $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="exercice_id" label="Exercice" col="3" :value="$val('exercice_id')" placeholder="— Aucun —">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected($val('exercice_id') == $ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.textarea name="mesures_maitrise" label="Mesures de maîtrise" col="12" :value="$val('mesures_maitrise')" :rows="2" />
        </div>
        <x-form.footer :cancel-url="route('risques.index')" />
    </div>
</form>
@endsection
