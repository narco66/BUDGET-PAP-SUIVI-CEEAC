@extends('layouts.app')
@section('titre', 'Nouvelle '.strtolower($config['label']))
@section('contenu')
<div class="card border-0 shadow-sm" style="max-width:760px;"><div class="card-body">
    <h5 class="fw-bold mb-3"><i class="bi {{ $config['icone'] }} me-1"></i>Nouvelle {{ strtolower($config['label']) }}</h5>
    <div class="alert alert-info small">
        <i class="bi bi-link-45deg me-1"></i>Rattaché(e) à {{ strtolower($config['parent_label']) }}
        <strong>{{ $parent->numero }}</strong>
        @if($type === 'liquidation' && isset($montantMax))
            · Reste liquidable : <strong>{{ number_format($montant,0,',',' ') }} XAF</strong>
            @if($montantMax < ($parent->montant_total ?? $parent->montant))
                <span class="text-muted">(engagement {{ number_format($parent->montant_total,0,',',' ') }} XAF)</span>
            @endif
        @else
            · Montant repris : <strong>{{ number_format($montant,0,',',' ') }} XAF</strong>
        @endif
    </div>

    <form method="POST" action="{{ route('depense.store', $type) }}">
        @csrf
        <input type="hidden" name="parent" value="{{ $parent->id }}">
        <div class="row g-3">
            @if($type === 'liquidation')
                <x-form.input name="montant" label="Montant à liquider (XAF)" type="number" col="4" step="0.01" :value="old('montant', $montant)" max="{{ $montant }}" required
                    :help="'Maximum : '.number_format($montant,0,',',' ').' XAF'" />
            @endif
            <x-form.input name="date" label="Date" type="date" col="4" :value="old('date', date('Y-m-d'))" />
            @foreach ($config['champs'] as $champ => $meta)
                @if ($meta['type'] === 'textarea')
                    <x-form.textarea :name="$champ" :label="$meta['label']" col="12" :value="old($champ)" :rows="2" />
                @elseif ($meta['type'] === 'select')
                    <x-form.select :name="$champ" :label="$meta['label']" col="4" :value="old($champ)" :required="$meta['requis'] ?? false" :placeholder="false">
                        @foreach ($meta['options'] as $k => $v)
                            <option value="{{ $k }}" @selected(old($champ)==$k)>{{ $v }}</option>
                        @endforeach
                    </x-form.select>
                @else
                    <x-form.input :name="$champ" :label="$meta['label']" col="8" :value="old($champ)" />
                @endif
            @endforeach
        </div>
        <x-form.actions :cancel-url="route('depense.index', $type)" cancel-label="Annuler" class="mt-3" />
    </form>
</div></div>
@endsection
