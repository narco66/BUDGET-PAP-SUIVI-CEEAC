@extends('layouts.app')
@section('titre', ($noeud ? 'Modifier' : 'Nouveau').' · '.$config['label'])

@php
$valeur = fn ($champ, $defaut = null) => old($champ, $noeud->$champ ?? $defaut);
@endphp

@section('contenu')
<div class="card border-0 shadow-sm" style="max-width:820px;"><div class="card-body">
    <h5 class="fw-bold mb-3"><i class="bi {{ $config['icone'] }} me-1"></i>{{ $noeud ? 'Modifier le' : 'Nouveau' }} {{ strtolower($config['label']) }}</h5>

    <form method="POST" action="{{ $noeud ? route('rbm.update', [$niveau, $noeud->id]) : route('rbm.store', $niveau) }}">
        @csrf
        @if($noeud) @method('PUT') @endif
        <input type="hidden" name="parent" value="{{ $parentId }}">
        <input type="hidden" name="exercice" value="{{ $exerciceId }}">

        <div class="row g-3">
            @foreach ($config['champs'] as $champ => $meta)
                @php $libelle = $meta['label'] ?? ucfirst(str_replace('_',' ',$champ)); @endphp

                @if ($meta['type'] === 'trimestres')
                    <div class="col-12">
                        <label class="form-label">{{ $libelle }}</label><br>
                        @foreach ([1,2,3,4] as $t)
                            <div class="form-check form-check-inline">
                                <input type="checkbox" name="trimestres[]" value="{{ $t }}" id="t{{ $t }}" class="form-check-input"
                                    @checked(collect(old('trimestres', $noeud ? array_keys(array_filter([1=>$noeud->trim_1,2=>$noeud->trim_2,3=>$noeud->trim_3,4=>$noeud->trim_4])) : []))->map(fn($v)=>(string)$v)->contains((string)$t))>
                                <label for="t{{ $t }}" class="form-check-label">{{ $t }}T</label>
                            </div>
                        @endforeach
                    </div>

                @elseif ($meta['type'] === 'textarea')
                    <x-form.textarea :name="$champ" :label="$libelle" col="12" :value="$valeur($champ)" :rows="2" />

                @elseif ($meta['type'] === 'select')
                    <x-form.select :name="$champ" :label="$libelle" col="6" :value="$valeur($champ, $meta['type']==='select' ? 'a_faire' : null)" placeholder="— Non défini —">
                        @if ($meta['source'] === 'statuts_tache')
                            @foreach ($options['statuts_tache'] as $k => $v)
                                <option value="{{ $k }}" @selected($valeur($champ, $meta['type']==='select' ? 'a_faire' : null)==$k)>{{ $v }}</option>
                            @endforeach
                        @else
                            @foreach ($options[$meta['source']] as $opt)
                                <option value="{{ $opt->id }}" @selected($valeur($champ)==$opt->id)>
                                    {{ $opt->code ?? '' }} {{ $opt->libelle ?? $opt->name ?? $opt->intitule ?? '' }}
                                </option>
                            @endforeach
                        @endif
                    </x-form.select>

                @elseif (in_array($meta['type'], ['number','decimal']))
                    <x-form.input :name="$champ" :label="$libelle" type="number" col="4" :step="$meta['type']==='decimal' ? '0.01' : '1'" :value="$valeur($champ, $meta['defaut'] ?? '')" />

                @elseif ($meta['type'] === 'date')
                    <x-form.input :name="$champ" :label="$libelle" type="date" col="4" :value="\Illuminate\Support\Str::of($valeur($champ))->substr(0,10)" />

                @else
                    <x-form.input :name="$champ" :label="$libelle" col="{{ $champ === 'code' ? 4 : 8 }}" :value="$valeur($champ)" :required="$meta['requis'] ?? false" />
                @endif
            @endforeach
        </div>

        <x-form.actions :cancel-url="route('rbm.arbre', $exerciceId)" cancel-label="Annuler" class="mt-3" />
    </form>
</div></div>
@endsection
