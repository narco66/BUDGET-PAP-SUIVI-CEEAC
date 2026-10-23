@extends('layouts.app')
@section('titre', ($element->exists ? 'Modifier' : 'Nouveau').' '.$label)
@php $val = fn ($k, $d = null) => old($k, $element->$k ?? $d); @endphp

@section('contenu')
<a href="{{ route('nomenclature.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Nomenclature</a>
<h4 class="fw-bold mb-3">{{ $element->exists ? 'Modifier le '.\Illuminate\Support\Str::lower($label).' '.$element->code : 'Nouveau '.\Illuminate\Support\Str::lower($label) }}</h4>

<form method="POST" action="{{ $element->exists ? route('nomenclature.update', ['niveau' => $niveau, 'id' => $element->id]) : route('nomenclature.store', $niveau) }}">
    @csrf @if($element->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            @if ($niveau === 'titre')
                <x-form.select name="sens" label="Sens budgétaire" col="4" :value="$val('sens')" required :options="$sensListe" />
            @else
                <x-form.select :name="$parentFk" :label="$parentLabel.' de rattachement'" col="6" :value="$val($parentFk)" required>
                    @foreach ($parents as $id => $libelle)
                        <option value="{{ $id }}" @selected($val($parentFk) == $id)>{{ $libelle }}</option>
                    @endforeach
                </x-form.select>
            @endif
            <x-form.input name="code" label="Code" col="3" :value="$val('code')" required />
            <x-form.input name="ordre" label="Ordre" type="number" col="2" min="0" :value="$val('ordre', 0)" />
            <x-form.input name="intitule" label="Intitulé" col="12" :value="$val('intitule')" required />
        </div>
        <x-form.footer :cancel-url="route('nomenclature.index')" />
    </div>
</form>
@endsection
