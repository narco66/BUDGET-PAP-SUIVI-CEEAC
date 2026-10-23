<div class="row g-3">
    <x-form.select name="plan_strategique_id" label="Plan stratégique" col="4" :value="$objectif->plan_strategique_id ?? ''" required>
        @foreach($plans as $p)
            <option value="{{ $p->id }}" @selected(old('plan_strategique_id', $objectif->plan_strategique_id ?? '')==$p->id)>{{ $p->reference }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="orientation_strategique_id" label="Orientation" col="4" :value="$objectif->orientation_strategique_id ?? ''" placeholder="—">
        @foreach($orientations as $o)
            <option value="{{ $o->id }}" @selected(old('orientation_strategique_id', $objectif->orientation_strategique_id ?? '')==$o->id)>{{ $o->code }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="code" label="Code" col="2" :value="$objectif->code ?? ''" required />
    <x-form.input name="ordre" label="Ordre" type="number" col="2" min="0" :value="$objectif->ordre ?? 0" />
    <x-form.input name="intitule" label="Intitulé" col="12" :value="$objectif->intitule ?? ''" required />
    <x-form.textarea name="description" label="Description" col="12" :value="$objectif->description ?? ''" :rows="2" />
    <x-form.select name="statut" label="Statut" col="3" :value="$objectif->statut ?? 'actif'"
        :options="['actif'=>'Actif','inactif'=>'Inactif','archive'=>'Archive']" />
</div>
<x-form.actions :cancel-url="route('strategie.objectifs.index')" />
