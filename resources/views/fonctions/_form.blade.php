@csrf
<div class="row g-3">
    <x-form.input name="code" label="Code" col="3" :value="$fonction->code ?? ''" required />
    <x-form.input name="libelle" label="Libellé" col="9" :value="$fonction->libelle ?? ''" required />
    <x-form.textarea name="description" label="Description" col="12" :value="$fonction->description ?? ''" :rows="2" />
    <x-form.checkbox name="actif" label="Fonction active" col="12" :checked="$fonction->actif ?? true" />
</div>
<x-form.actions :cancel-url="route('fonctions.index')" />
