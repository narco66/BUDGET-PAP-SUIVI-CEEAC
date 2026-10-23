<div class="row g-3">
    <x-form.select name="plan_strategique_id" label="Plan stratégique" col="6" :value="$orientation->plan_strategique_id ?? ''" required>
        @foreach($plans as $p)
            <option value="{{ $p->id }}" @selected(old('plan_strategique_id', $orientation->plan_strategique_id ?? '')==$p->id)>{{ $p->reference }} · {{ Str::limit($p->intitule, 35) }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="vision_id" label="Vision" col="6" :value="$orientation->vision_id ?? ''" placeholder="— Auto via plan —">
        @foreach($visions as $v)
            <option value="{{ $v->id }}" @selected(old('vision_id', $orientation->vision_id ?? '')==$v->id)>{{ $v->reference }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="code" label="Code" col="2" :value="$orientation->code ?? ''" required />
    <x-form.input name="intitule" label="Intitulé" col="6" :value="$orientation->intitule ?? ''" required />
    <x-form.input name="ordre" label="Ordre" type="number" col="2" min="0" :value="$orientation->ordre ?? 0" />
    <x-form.select name="statut" label="Statut" col="2" :value="$orientation->statut ?? 'actif'"
        :options="['actif'=>'Actif','inactif'=>'Inactif','archive'=>'Archive']" />
    <x-form.textarea name="description" label="Description" col="12" :value="$orientation->description ?? ''" :rows="2" />
</div>
<x-form.actions :cancel-url="route('strategie.orientations.index')" />
