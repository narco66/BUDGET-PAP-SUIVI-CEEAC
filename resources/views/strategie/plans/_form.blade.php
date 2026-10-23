<div class="row g-3">
    <x-form.input name="reference" label="Référence" col="3" :value="$plan->reference ?? ''" required />
    <x-form.input name="intitule" label="Intitulé" col="5" :value="$plan->intitule ?? ''" required />
    <x-form.select name="vision_id" label="Vision" col="4" :value="$plan->vision_id ?? ''" placeholder="— Aucune —">
        @foreach($visions as $v)
            <option value="{{ $v->id }}" @selected(old('vision_id', $plan->vision_id ?? '')==$v->id)>{{ $v->reference }} · {{ Str::limit($v->intitule, 40) }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="date_debut" label="Date début" type="date" col="3" :value="optional($plan->date_debut)->format('Y-m-d')" required />
    <x-form.input name="date_fin" label="Date fin" type="date" col="3" :value="optional($plan->date_fin)->format('Y-m-d')" required />
    <x-form.select name="statut" label="Statut" col="3" :value="$plan->statut ?? 'brouillon'" required
        :options="['brouillon'=>'Brouillon','actif'=>'Actif','archive'=>'Archive','obsolete'=>'Obsolete']" />
    <x-form.input name="version" label="Version" type="number" col="3" min="1" :value="$plan->version ?? 1" />
    <x-form.textarea name="description" label="Description" col="12" :value="$plan->description ?? ''" :rows="3" />
</div>
<x-form.actions :cancel-url="route('strategie.plans.index')" />
