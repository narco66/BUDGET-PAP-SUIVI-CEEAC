@extends('layouts.app')
@section('titre', $contrat->exists ? 'Modifier le contrat' : 'Nouveau contrat')
@php
    $val = fn ($k, $d = null) => old($k, $contrat->$k ?? $d);
    $date = fn ($k) => ($v = old($k, $contrat->$k ?? null)) instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
@endphp

@section('contenu')
<a href="{{ route('contrats.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Contrats</a>
<h4 class="fw-bold mb-3">{{ $contrat->exists ? 'Modifier le contrat '.$contrat->numero : 'Nouveau contrat' }}</h4>

<form method="POST" action="{{ $contrat->exists ? route('contrats.update', $contrat) : route('contrats.store') }}">
    @csrf @if($contrat->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="exercice_id" label="Exercice" col="3" :value="$val('exercice_id')" required :placeholder="false">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" @selected($val('exercice_id') == $ex->id)>{{ $ex->annee }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="numero" label="N° (auto si vide)" col="3" :value="$val('numero')" placeholder="CTR-2026-001" />
            <x-form.input name="objet" label="Objet" col="6" :value="$val('objet')" required />
            <x-form.input name="titulaire" label="Titulaire (cocontractant)" col="6" :value="$val('titulaire')" required />
            <x-form.select name="type_contrat" label="Type" col="3" :value="$val('type_contrat')" required :options="$types" :placeholder="false" />
            <x-form.select name="statut" label="Statut" col="3" :value="$val('statut')" required :options="$statuts" :placeholder="false" />
            <x-form.input name="montant" label="Montant (XAF)" type="number" col="3" step="0.01" min="0" :value="$val('montant', 0)" required />
            <x-form.select name="marche_id" label="Marché lié" col="3" :value="$val('marche_id')" placeholder="—">
                @foreach ($marches as $m)
                    <option value="{{ $m->id }}" @selected($val('marche_id') == $m->id)>{{ $m->numero }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="engagement_id" label="Engagement lié" col="6" :value="$val('engagement_id')" placeholder="—">
                @foreach ($engagements as $e)
                    <option value="{{ $e->id }}" @selected($val('engagement_id') == $e->id)>{{ $e->numero }} — {{ \Illuminate\Support\Str::limit($e->objet, 35) }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="structure_id" label="Structure" col="3" :value="$val('structure_id')" placeholder="—">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected($val('structure_id') == $s->id)>{{ $s->sigle ?? $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="date_signature" label="Date de signature" type="date" col="4" :value="$date('date_signature')" />
            <x-form.input name="date_debut" label="Date de début" type="date" col="4" :value="$date('date_debut')" />
            <x-form.input name="date_fin" label="Date de fin" type="date" col="4" :value="$date('date_fin')" />
            <x-form.textarea name="observations" label="Observations" col="12" :value="$val('observations')" :rows="2" />
        </div>
        <x-form.footer :cancel-url="route('contrats.index')" />
    </div>
</form>
@endsection
