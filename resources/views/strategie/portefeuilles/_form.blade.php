<div class="row g-3">
    <x-form.input name="reference" label="Référence" col="3" :value="$portefeuille->reference ?? ''" required />
    <x-form.input name="intitule" label="Intitulé" col="6" :value="$portefeuille->intitule ?? ''" required />
    <x-form.select name="statut" label="Statut" col="3" :value="$portefeuille->statut ?? 'actif'"
        :options="['actif'=>'Actif','archive'=>'Archive']" />
    <x-form.select name="exercice_id" label="Exercice" col="4" :value="$portefeuille->exercice_id ?? ''" placeholder="—">
        @foreach($exercices as $ex)
            <option value="{{ $ex->id }}" @selected(old('exercice_id', $portefeuille->exercice_id ?? '')==$ex->id)>{{ $ex->annee }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="responsable_user_id" label="Responsable" col="4" :value="$portefeuille->responsable_user_id ?? ''" placeholder="—">
        @foreach($responsables as $u)
            <option value="{{ $u->id }}" @selected(old('responsable_user_id', $portefeuille->responsable_user_id ?? '')==$u->id)>{{ $u->name }}</option>
        @endforeach
    </x-form.select>
    <x-form.textarea name="description" label="Description" col="12" :value="$portefeuille->description ?? ''" :rows="2" />
    <div class="col-12">
        <label class="form-label">Projets rattachés</label>
        <select name="projet_ids[]" class="form-select @error('projet_ids') is-invalid @enderror @error('projet_ids.*') is-invalid @enderror" multiple size="6">
            @php $selected = old('projet_ids', isset($portefeuille) && $portefeuille->relationLoaded('projets') ? $portefeuille->projets->pluck('id')->all() : []); @endphp
            @foreach($projetsDisponibles as $pr)
                <option value="{{ $pr->id }}" @selected(in_array($pr->id, $selected))>{{ $pr->code }} · {{ Str::limit($pr->intitule, 50) }}</option>
            @endforeach
        </select>
        @error('projet_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @error('projet_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="form-text">Maintenir Ctrl pour sélection multiple.</div>
    </div>
</div>
<x-form.actions :cancel-url="route('strategie.portefeuilles.index')" />
