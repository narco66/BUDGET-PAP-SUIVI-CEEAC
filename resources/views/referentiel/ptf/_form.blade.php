@csrf
@php $p = $partenaire ?? null; @endphp
<div class="row g-3">
    <x-form.input name="code" label="Code" col="3" :value="$p->code ?? ''" required />
    <x-form.input name="libelle" label="Libellé" col="6" :value="$p->libelle ?? ''" required />
    <x-form.input name="sigle" label="Sigle" col="3" :value="$p->sigle ?? ''" />
    <x-form.select name="type" label="Type" col="4" :value="$p->type ?? ''" required :options="\App\Models\PartenaireFinancier::TYPES" />
    <x-form.input name="pays" label="Pays" col="4" :value="$p->pays ?? ''" />
    <x-form.checkbox name="actif" label="Partenaire actif" col="4" :checked="$p->actif ?? true" />
    <x-form.textarea name="description" label="Description" col="12" :value="$p->description ?? ''" :rows="2" />
</div>
<x-form.actions :cancel-url="route('partenaires.index')" />
