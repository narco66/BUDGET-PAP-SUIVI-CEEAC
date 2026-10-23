@extends('layouts.app')
@section('titre', $marche->exists ? 'Modifier le marché' : 'Nouveau marché')
@php
    $val = fn ($k, $d = null) => old($k, $marche->$k ?? $d);
    $date = fn ($k) => ($v = old($k, $marche->$k ?? null)) instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
@endphp

@section('contenu')
<a href="{{ route('marches.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Marchés</a>
<h4 class="fw-bold mb-3">{{ $marche->exists ? 'Modifier le marché '.$marche->numero : 'Nouveau marché' }}</h4>

<form method="POST" action="{{ $marche->exists ? route('marches.update', $marche) : route('marches.store') }}">
    @csrf @if($marche->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="exercice_id" label="Exercice" col="3" :value="$val('exercice_id')" required :placeholder="false">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected($val('exercice_id') == $ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="numero" label="N° (auto si vide)" col="3" :value="$val('numero')" placeholder="MAR-2026-001" />
            <x-form.input name="objet" label="Objet" col="6" :value="$val('objet')" required />
            <x-form.select name="type_marche" label="Type" col="4" :value="$val('type_marche')" required :options="$types" :placeholder="false" />
            <x-form.select name="mode_passation" label="Mode de passation" col="4" :value="$val('mode_passation')" required :options="$modes" :placeholder="false" />
            <x-form.select name="statut" label="Statut" col="4" :value="$val('statut')" required :options="$statuts" :placeholder="false" />
            <x-form.input name="montant_estime" label="Montant estimé (XAF)" type="number" col="3" step="0.01" min="0" :value="$val('montant_estime', 0)" required />
            <x-form.input name="montant_attribue" label="Montant attribué (XAF)" type="number" col="3" step="0.01" min="0" :value="$val('montant_attribue', 0)" />
            <x-form.input name="attributaire" label="Attributaire" col="6" :value="$val('attributaire')" />
            <x-form.input name="date_lancement" label="Date de lancement" type="date" col="3" :value="$date('date_lancement')" />
            <x-form.input name="date_attribution" label="Date d'attribution" type="date" col="3" :value="$date('date_attribution')" />
            <x-form.select name="structure_id" label="Structure" col="3" :value="$val('structure_id')" placeholder="—">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->sigle ?? $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="expression_besoin_id" label="Expression de besoin" col="3" :value="$val('expression_besoin_id')" placeholder="—">
                @foreach ($besoins as $b)
                    <option value="{{ $b->id }}" @selected($val('expression_besoin_id') == $b->id)>{{ $b->numero }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="engagement_id" label="Engagement lié" col="6" :value="$val('engagement_id')" placeholder="—">
                @foreach ($engagements as $e)
                    <option value="{{ $e->id }}" @selected($val('engagement_id') == $e->id)>{{ $e->numero }} — {{ \Illuminate\Support\Str::limit($e->objet, 40) }}</option>
                @endforeach
            </x-form.select>
            <x-form.textarea name="observations" label="Observations" col="12" :value="$val('observations')" :rows="2" />
        </div>
        <x-form.footer :cancel-url="route('marches.index')" />
    </div>
</form>
@endsection
