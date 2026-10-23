@extends('layouts.app')
@section('titre', 'Édition cadre logique · '.$exercice->annee)

@section('contenu')
@include('rbm._nav-module', ['exercice' => $exercice, 'page' => 'cadre'])

<form method="POST" action="{{ route('rbm.cadre-logique.update', $exercice) }}">
    @csrf
    @method('PUT')

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Document</div>
        <div class="card-body">
            <div class="row g-3">
                <x-form.input name="intitule" label="Intitulé" col="6"
                    :value="old('intitule', $document->intitule ?? 'Cadre logique '.$exercice->annee)" />
                <x-form.textarea name="description" label="Description / contexte" col="12"
                    :value="old('description', $document->description ?? '')" :rows="2" />
            </div>
        </div>
    </div>

    @php $axeIndex = 0; @endphp
    @foreach($piliers as $pilier)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white">
            <strong>{{ $pilier['code'] }}</strong> — {{ $pilier['intitule'] }}
        </div>
        <div class="card-body">
            @foreach($pilier['axes'] as $axe)
            <div class="border rounded p-3 mb-3">
                <input type="hidden" name="axes[{{ $axeIndex }}][id]" value="{{ $axe['id'] }}">
                <h3 class="h6 mb-2"><code>{{ $axe['code'] }}</code> {{ $axe['intitule'] }}</h3>
                <x-form.textarea
                    :name="'axes['.$axeIndex.'][objectif_strategique]'"
                    label="Objectif stratégique"
                    label-class="small"
                    input-class="form-control-sm"
                    col="12"
                    :value="$axe['objectif_strategique']"
                    :rows="3" />
                <x-form.textarea
                    :name="'axes['.$axeIndex.'][commentaire]'"
                    label="Commentaire / hypothèses (document cadre logique)"
                    label-class="small"
                    input-class="form-control-sm"
                    col="12"
                    :value="$axe['commentaire']"
                    :rows="2" />
            </div>
            @php $axeIndex++; @endphp
            @endforeach
        </div>
    </div>
    @endforeach

    <x-form.actions :cancel-url="route('rbm.cadre-logique', $exercice)" />
</form>
@endsection
