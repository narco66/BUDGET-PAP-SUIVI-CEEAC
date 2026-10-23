<div class="row g-3">
    <x-form.select name="plan_strategique_id" label="Plan stratégique" col="6" :value="$programmation->plan_strategique_id ?? ''" required>
        @foreach($plans as $p)
            <option value="{{ $p->id }}" @selected(old('plan_strategique_id', $programmation->plan_strategique_id ?? '')==$p->id)>{{ $p->reference }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="reference" label="Référence" col="3" :value="$programmation->reference ?? ''" required />
    <x-form.select name="statut" label="Statut" col="3" :value="$programmation->statut ?? 'brouillon'"
        :options="['brouillon'=>'Brouillon','actif'=>'Actif','archive'=>'Archive']" />
    <x-form.input name="annee_debut" label="Année début" type="number" col="3" min="2000" :value="$programmation->annee_debut ?? date('Y')" required />
    <x-form.input name="annee_fin" label="Année fin" type="number" col="3" min="2000" :value="$programmation->annee_fin ?? date('Y')+4" required />
    <x-form.input name="version" label="Version" type="number" col="3" min="1" :value="$programmation->version ?? 1" />
    <x-form.textarea name="description" label="Description" col="12" :value="$programmation->description ?? ''" :rows="2" />
</div>
<x-form.actions :cancel-url="route('strategie.programmations-pluri.index')" />
