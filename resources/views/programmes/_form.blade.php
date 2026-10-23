@csrf
<div class="row g-3">
    <x-form.input name="code" label="Code" col="3" :value="$programme->code ?? ''" required />
    <x-form.input name="intitule" label="Intitulé" col="9" :value="$programme->intitule ?? ''" required />
    <x-form.select name="structure_id" label="Structure" col="6" :value="$programme->structure_id ?? ''" required>
        @foreach ($structures as $s)
            <option value="{{ $s->id }}" @selected(old('structure_id', $programme->structure_id ?? '')==$s->id)>{{ $s->code }} · {{ $s->libelle }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="pilier_id" label="Pilier (optionnel)" col="6" :value="$programme->pilier_id ?? ''" placeholder="— Aucun —">
        @foreach ($piliers as $pi)
            <option value="{{ $pi->id }}" @selected(old('pilier_id', $programme->pilier_id ?? '')==$pi->id)>{{ $pi->code }} · {{ $pi->intitule }}</option>
        @endforeach
    </x-form.select>
    <x-form.textarea name="description" label="Description" col="12" :value="$programme->description ?? ''" :rows="2" />
    <x-form.checkbox name="actif" label="Programme actif" col="12" :checked="$programme->actif ?? true" />
</div>
<x-form.actions :cancel-url="route('programmes.index')" />
