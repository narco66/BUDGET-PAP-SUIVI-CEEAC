@csrf
@if (request('retour'))<input type="hidden" name="retour" value="{{ request('retour') }}">@endif
<div class="row g-3">
    <x-form.input name="code" label="Code" col="3" :value="$structure->code ?? ''" required />
    <x-form.input name="libelle" label="Libellé" col="6" :value="$structure->libelle ?? ''" required />
    <x-form.input name="sigle" label="Sigle" col="3" :value="$structure->sigle ?? ''" />
    <x-form.select name="type" label="Type" col="4" :value="$structure->type ?? ''" required
        :options="['presidence'=>'Présidence','vice_presidence'=>'Vice-Présidence','secretariat_general'=>'Secrétariat Général','commissariat'=>'Commissariat','departement'=>'Département','direction'=>'Direction','service'=>'Service','cellule'=>'Cellule']" />
    <x-form.select name="parent_id" label="Structure parente" col="4" :value="old('parent_id', $structure->parent_id ?? request('parent_id'))" placeholder="— Aucune (racine) —">
        @foreach ($parents as $p)
            <option value="{{ $p->id }}" @selected(old('parent_id', $structure->parent_id ?? request('parent_id'))==$p->id)>{{ $p->code }} · {{ $p->libelle }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="responsable_user_id" label="Responsable" col="4" :value="$structure->responsable_user_id ?? ''" placeholder="— Non défini —">
        @foreach ($responsables as $u)
            <option value="{{ $u->id }}" @selected(old('responsable_user_id', $structure->responsable_user_id ?? '')==$u->id)>{{ $u->name }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="ordre" label="Ordre" type="number" col="2" :value="$structure->ordre ?? 0" min="0" />
    <x-form.checkbox name="actif" label="Structure active" col="10" :checked="$structure->actif ?? true" />
    <x-form.textarea name="description" label="Description" col="12" :value="$structure->description ?? ''" :rows="2" />
</div>
<x-form.actions :cancel-url="route('structures.index')" />
