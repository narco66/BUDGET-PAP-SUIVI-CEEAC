@extends('layouts.app')
@section('titre', $modele->exists ? 'Modifier modèle' : 'Nouveau modèle de rapport')
@php
    $val = fn ($k, $d = null) => old($k, $modele->$k ?? $d);
    $cols = old('colonnes', $modele->colonnes ?? []);
@endphp

@section('contenu')
<a href="{{ route('reporting.modeles.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Rapports personnalisés</a>
<h4 class="fw-bold mb-3">{{ $modele->exists ? 'Modifier '.$modele->code : 'Nouveau modèle de rapport' }}</h4>

<form method="POST" action="{{ $modele->exists ? route('reporting.modeles.update', $modele) : route('reporting.modeles.store') }}"
      x-data="{ source: @js($val('source', 'lignes_budgetaires')), champs: @js($sources) }">
    @csrf @if($modele->exists)@method('PUT')@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.input name="titre" label="Titre du rapport" col="8" :value="$val('titre')" required />
            <x-form.select name="source" label="Source de données" col="4" :value="$val('source', 'lignes_budgetaires')" required :placeholder="false"
                x-model="source" @change="source = $event.target.value">
                @foreach ($sources as $cle => $def)
                    <option value="{{ $cle }}" @selected($val('source', 'lignes_budgetaires') === $cle)>{{ $def['label'] }}</option>
                @endforeach
            </x-form.select>
            <x-form.textarea name="description" label="Description (optionnelle)" col="12" :value="$val('description')" :rows="2" />

            <div class="col-12">
                <label class="form-label">Colonnes à inclure <span class="text-danger">*</span></label>
                @foreach ($sources as $cle => $def)
                    <div class="border rounded p-3 mb-2" x-show="source === @js($cle)" x-cloak>
                        <div class="row g-2">
                            @foreach ($def['champs'] as $field => $meta)
                                <div class="col-md-4 col-lg-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="{{ $field }}" id="col-{{ $cle }}-{{ $field }}"
                                            @checked(in_array($field, $cols, true))>
                                        <label class="form-check-label small" for="col-{{ $cle }}-{{ $field }}">{{ $meta['label'] }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @error('colonnes')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <x-form.checkbox name="partage" label="Partager avec tous les utilisateurs autorisés au reporting" col="6" :checked="(bool) $val('partage', true)" />
            @if ($modele->exists)
                <x-form.checkbox name="actif" label="Modèle actif" col="6" :checked="(bool) $val('actif', true)" />
            @endif
        </div>
        <x-form.footer :cancel-url="route('reporting.modeles.index')" />
    </div>
</form>
@endsection

@section('scripts')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>document.addEventListener('alpine:init', () => {});</script>
@endsection
