@csrf
<div class="row g-3">
    <x-form.input name="code" label="Code" col="3" :value="$poste->code ?? ''" required />
    <x-form.input name="libelle" label="Libellé" col="9" :value="$poste->libelle ?? ''" required />
    <x-form.select name="fonction_id" label="Fonction liée" col="6" :value="$poste->fonction_id ?? ''" placeholder="— Aucune —">
        @foreach ($fonctions as $f)
            <option value="{{ $f->id }}" @selected(old('fonction_id', $poste->fonction_id ?? '') == $f->id)>{{ $f->libelle }} ({{ $f->code }})</option>
        @endforeach
    </x-form.select>
    <x-form.textarea name="description" label="Description" col="12" :value="$poste->description ?? ''" :rows="2" />
    <x-form.checkbox name="actif" label="Poste actif" col="12" :checked="$poste->actif ?? true" />
</div>
<x-form.actions :cancel-url="route('postes.index')" />
