@extends('layouts.app')
@section('titre', 'Modifier '.$ligne->code)

@section('contenu')
<a href="{{ route('structure-budget.ligne.show', ['ligne' => $ligne, 'exercice' => $exercice->id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Fiche ligne</a>
<h4 class="fw-bold mb-3">Modifier la ligne <span class="font-monospace text-primary">{{ $ligne->code }}</span></h4>

<form method="POST" action="{{ route('structure-budget.ligne.update', $ligne) }}">
    @csrf @method('PUT')
    <input type="hidden" name="exercice_id" value="{{ $ligne->exercice_id }}">
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="structure_id" label="Structure" col="6" :value="$ligne->structure_id" required :placeholder="false">
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected(old('structure_id', $ligne->structure_id) == $s->id)>{{ $s->code }} · {{ $s->sigle ?: $s->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="rubrique_id" label="Rubrique" col="6" :value="$ligne->rubrique_id" required :placeholder="false">
                @foreach ($rubriques as $r)
                    <option value="{{ $r->id }}" @selected(old('rubrique_id', $ligne->rubrique_id) == $r->id)>{{ $r->code }} · {{ \Illuminate\Support\Str::limit($r->intitule, 40) }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="code" label="Code" col="4" :value="$ligne->code" required input-class="font-monospace" />
            <x-form.select name="source_financement_id" label="Source de financement" col="4" :value="$ligne->source_financement_id" placeholder="— Aucune —">
                @foreach ($sources as $sf)
                    <option value="{{ $sf->id }}" @selected(old('source_financement_id', $ligne->source_financement_id) == $sf->id)>{{ $sf->code }} · {{ $sf->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="montant_ouvert" label="Montant ouvert (XAF)" type="number" col="4" min="0" step="0.01"
                :value="$ligne->montant_ouvert" required
                :help="$ligne->montant_engage > 0 ? 'Engagé : '.number_format((float)$ligne->montant_engage, 0, ',', ' ').' XAF (minimum autorisé).' : null" />
            <x-form.input name="intitule" label="Intitulé" col="12" :value="$ligne->intitule" maxlength="255" />
        </div>
        <x-form.footer :cancel-url="route('structure-budget.ligne.show', $ligne)" />
    </div>
</form>
@endsection
