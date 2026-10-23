@extends('layouts.app')
@section('titre', $app->exists ? 'Modifier application' : 'Nouvelle application')

@section('contenu')
<div class="mb-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-plug me-2"></i>{{ $app->exists ? 'Modifier' : 'Nouvelle' }} application</h4>
</div>
@include('admin.interoperabilite._nav-module', ['page' => 'apps'])

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ $app->exists ? route('admin.interoperabilite.apps.update', $app) : route('admin.interoperabilite.apps.store') }}">
            @csrf
            @if($app->exists) @method('PUT') @endif

            <div class="row g-3">
                <x-form.input name="code" label="Code" col="4" :value="old('code', $app->code)" required maxlength="50" />
                <x-form.input name="libelle" label="Libellé" col="8" :value="old('libelle', $app->libelle)" required />
                <x-form.textarea name="description" label="Description" col="12" :value="old('description', $app->description)" :rows="2" />
                <x-form.select name="type" label="Type" col="4" :value="old('type', $app->type)" required :placeholder="false">
                    @foreach(config('interoperabilite.app_types', []) as $val => $label)
                        <option value="{{ $val }}" @selected(old('type', $app->type) === $val)>{{ $label }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="contact_email" label="E-mail contact" type="email" col="4" :value="old('contact_email', $app->contact_email)" />
                <x-form.select name="statut" label="Statut" col="4" :value="old('statut', $app->statut)" required :placeholder="false">
                    @foreach(['actif' => 'Actif', 'suspendu' => 'Suspendu', 'revoque' => 'Révoqué'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('statut', $app->statut) === $val)>{{ $label }}</option>
                    @endforeach
                </x-form.select>
                <div class="col-12">
                    <label class="form-label d-block">Scopes autorisés <span class="text-danger">*</span></label>
                    @php $scopes = old('scopes_autorises', $app->scopes_autorises ?? ['lecture']); @endphp
                    @foreach(config('interoperabilite.token_abilities', []) as $scope)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="scopes_autorises[]" value="{{ $scope }}" id="scope_{{ $scope }}"
                                @checked(in_array($scope, $scopes, true))>
                            <label class="form-check-label" for="scope_{{ $scope }}"><code>{{ $scope }}</code></label>
                        </div>
                    @endforeach
                    @error('scopes_autorises')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            <x-form.actions :cancel-url="route('admin.interoperabilite.apps.index')" cancel-label="Annuler" class="mt-4" />
        </form>
    </div>
</div>
@endsection
