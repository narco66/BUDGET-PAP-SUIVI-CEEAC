@csrf
@php $s = $source ?? null; @endphp
<div class="row g-3">
    <x-form.input name="code" label="Code" col="3" :value="$s->code ?? ''" required />
    <x-form.input name="libelle" label="Libellé" col="6" :value="$s->libelle ?? ''" required />
    <x-form.select name="type" label="Type" col="3" :value="$s->type ?? ''" required :options="\App\Models\SourceFinancement::TYPES" />
    <x-form.select name="classification" label="Classification" col="4" :value="$s->classification ?? ''" placeholder="— Non définie —" :options="\App\Models\SourceFinancement::CLASSIFICATIONS" />
    <x-form.select name="partenaire_id" label="Partenaire (PTF)" col="4" :value="$s->partenaire_id ?? ''" placeholder="— Aucun —">
        @foreach ($partenaires as $p)
            <option value="{{ $p->id }}" @selected(old('partenaire_id', $s->partenaire_id ?? '')==$p->id)>{{ $p->code }} · {{ $p->libelle }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="bailleur" label="Bailleur (texte libre)" col="4" :value="$s->bailleur ?? ''" />
    <x-form.textarea name="description" label="Description" col="12" :value="$s->description ?? ''" :rows="2" />
    <x-form.checkbox name="actif" label="Source active" col="12" :checked="$s->actif ?? true" />
</div>
<x-form.actions :cancel-url="route('sources.index')" />
