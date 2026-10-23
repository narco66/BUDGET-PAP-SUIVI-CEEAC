<div class="row g-3">
    <x-form.select name="programme_strategique_id" label="Programme stratégique" col="6" :value="$projet->programme_strategique_id ?? ''" required>
        @foreach($programmes as $pg)
            <option value="{{ $pg->id }}" @selected(old('programme_strategique_id', $projet->programme_strategique_id ?? '')==$pg->id)>{{ $pg->code }} · {{ Str::limit($pg->intitule, 35) }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="code" label="Code" col="3" :value="$projet->code ?? ''" required />
    <x-form.select name="responsable_user_id" label="Responsable" col="3" :value="$projet->responsable_user_id ?? ''" required>
        @foreach($responsables as $u)
            <option value="{{ $u->id }}" @selected(old('responsable_user_id', $projet->responsable_user_id ?? '')==$u->id)>{{ $u->name }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="intitule" label="Intitulé" col="12" :value="$projet->intitule ?? ''" required />
    <x-form.select name="statut" label="Statut" col="3" :value="$projet->statut ?? 'planifie'"
        :options="['planifie'=>'Planifie','en_cours'=>'En cours','suspendu'=>'Suspendu','acheve'=>'Acheve','archive'=>'Archive']" />
    <x-form.select name="cycle_vie" label="Cycle de vie" col="3" :value="$projet->cycle_vie ?? 'initiation'"
        :options="['initiation'=>'Initiation','planification'=>'Planification','execution'=>'Execution','cloture'=>'Cloture']" />
    <x-form.input name="date_debut" label="Date début" type="date" col="3" :value="optional($projet->date_debut)->format('Y-m-d')" />
    <x-form.input name="date_fin" label="Date fin" type="date" col="3" :value="optional($projet->date_fin)->format('Y-m-d')" />
    <x-form.input name="budget_previsionnel" label="Budget prévisionnel" type="number" step="0.01" min="0" col="4" :value="$projet->budget_previsionnel ?? 0" />
    <x-form.input name="budget_engage" label="Budget engagé" type="number" step="0.01" min="0" col="4" :value="$projet->budget_engage ?? 0" />
    <x-form.select name="pilier_id" label="Pilier RBM" col="4" :value="$projet->pilier_id ?? ''" placeholder="—">
        @foreach($piliers as $pi)
            <option value="{{ $pi->id }}" @selected(old('pilier_id', $projet->pilier_id ?? '')==$pi->id)>{{ $pi->code }}</option>
        @endforeach
    </x-form.select>
    <x-form.textarea name="description" label="Description" col="12" :value="$projet->description ?? ''" :rows="2" />
</div>
<x-form.actions :cancel-url="route('strategie.projets.index')" />
