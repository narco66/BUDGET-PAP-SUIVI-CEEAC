<div class="row g-3">
    <x-form.select name="orientation_strategique_id" label="Orientation stratégique" col="6" :value="$programme->orientation_strategique_id ?? ''" required>
        @foreach($orientations as $o)
            <option value="{{ $o->id }}" @selected(old('orientation_strategique_id', $programme->orientation_strategique_id ?? '')==$o->id)>{{ $o->code }} · {{ Str::limit($o->intitule, 35) }} ({{ $o->planStrategique?->reference }})</option>
        @endforeach
    </x-form.select>
    <x-form.input name="code" label="Code" col="3" :value="$programme->code ?? ''" required />
    <x-form.select name="statut" label="Statut" col="3" :value="$programme->statut ?? 'brouillon'"
        :options="['brouillon'=>'Brouillon','actif'=>'Actif','archive'=>'Archive']" />
    <x-form.input name="intitule" label="Intitulé" col="12" :value="$programme->intitule ?? ''" required />
    <x-form.select name="responsable_user_id" label="Responsable" col="4" :value="$programme->responsable_user_id ?? ''" placeholder="—">
        @foreach($responsables as $u)
            <option value="{{ $u->id }}" @selected(old('responsable_user_id', $programme->responsable_user_id ?? '')==$u->id)>{{ $u->name }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="pilier_id" label="Pilier RBM (pont)" col="4" :value="$programme->pilier_id ?? ''" placeholder="—">
        @foreach($piliers as $pi)
            <option value="{{ $pi->id }}" @selected(old('pilier_id', $programme->pilier_id ?? '')==$pi->id)>{{ $pi->code }} · {{ Str::limit($pi->intitule, 30) }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="budget_previsionnel" label="Budget prévisionnel (XAF)" type="number" step="0.01" min="0" col="4" :value="$programme->budget_previsionnel ?? 0" />
    <x-form.input name="date_debut" label="Date début" type="date" col="3" :value="optional($programme->date_debut)->format('Y-m-d')" />
    <x-form.input name="date_fin" label="Date fin" type="date" col="3" :value="optional($programme->date_fin)->format('Y-m-d')" />
    <x-form.textarea name="objectifs" label="Objectifs" col="6" :value="$programme->objectifs ?? ''" :rows="2" />
    <x-form.textarea name="resultats_attendus" label="Résultats attendus" col="12" :value="$programme->resultats_attendus ?? ''" :rows="2" />
</div>
<x-form.actions :cancel-url="route('strategie.programmes.index')" />
