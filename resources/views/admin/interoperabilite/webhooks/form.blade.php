@extends('layouts.app')
@section('titre', $webhook->exists ? 'Modifier webhook' : 'Nouveau webhook')

@section('contenu')
<div class="mb-2">
    <h4 class="fw-bold mb-0">{{ $webhook->exists ? 'Modifier' : 'Nouveau' }} webhook</h4>
</div>
@include('admin.interoperabilite._nav-module', ['page' => 'webhooks'])

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ $webhook->exists ? route('admin.interoperabilite.webhooks.update', $webhook) : route('admin.interoperabilite.webhooks.store') }}">
            @csrf
            @if($webhook->exists) @method('PUT') @endif

            <div class="row g-3">
                <x-form.input name="libelle" label="Libellé" col="6" :value="old('libelle', $webhook->libelle)" required />
                <x-form.select name="evenement" label="Événement" col="6" :value="old('evenement', $webhook->evenement)" required :placeholder="false">
                    @foreach($evenements->groupBy('module') as $module => $items)
                        <optgroup label="{{ ucfirst($module) }}">
                            @foreach($items as $evt)
                                <option value="{{ $evt->code }}" @selected(old('evenement', $webhook->evenement)===$evt->code)>{{ $evt->code }} — {{ $evt->libelle }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-form.select>
                <x-form.input name="url" label="URL de callback" type="url" col="8" :value="old('url', $webhook->url)" required />
                <x-form.select name="methode" label="Méthode" col="2" :value="old('methode', $webhook->methode ?? 'POST')" :placeholder="false">
                    @foreach(['POST','PUT'] as $m)
                        <option value="{{ $m }}" @selected(old('methode', $webhook->methode ?? 'POST')===$m)>{{ $m }}</option>
                    @endforeach
                </x-form.select>
                <x-form.checkbox name="actif" label="Actif" col="2" :checked="old('actif', $webhook->actif)" />
                <x-form.select name="connected_app_id" label="Application connectée" col="6" :value="old('connected_app_id', $webhook->connected_app_id)" placeholder="— Aucune —">
                    @foreach($apps as $app)
                        <option value="{{ $app->id }}" @selected((string) old('connected_app_id', $webhook->connected_app_id)===(string) $app->id)>{{ $app->code }} — {{ $app->libelle }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="secret" label="Secret HMAC {{ $webhook->exists ? '(laisser vide pour conserver)' : '' }}" col="6" :placeholder="$webhook->exists ? '••••••••' : 'Généré automatiquement si vide'" />
            </div>

            <x-form.actions :cancel-url="route('admin.interoperabilite.webhooks.index')" cancel-label="Annuler" class="mt-4" />
        </form>
    </div>
</div>
@endsection
