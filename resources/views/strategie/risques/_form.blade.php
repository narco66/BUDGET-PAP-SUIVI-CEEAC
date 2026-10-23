<div class="row g-3">
    <x-form.select name="plan_strategique_id" label="Plan stratégique" col="4" :value="$risque->plan_strategique_id ?? ''" required>
        @foreach($plans as $p)
            <option value="{{ $p->id }}" @selected(old('plan_strategique_id', $risque->plan_strategique_id ?? '')==$p->id)>{{ $p->reference }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="code" label="Code" col="3" :value="$risque->code ?? ''" required />
    <x-form.select name="projet_id" label="Projet" col="3" :value="$risque->projet_id ?? ''" placeholder="—">
        @foreach($projets as $p)
            <option value="{{ $p->id }}" @selected(old('projet_id', $risque->projet_id ?? '')==$p->id)>{{ $p->code }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="statut" label="Statut" col="2" :value="$risque->statut ?? 'identifie'"
        :options="['identifie'=>'Identifie','en_traitement'=>'En traitement','mitige'=>'Mitige','cloture'=>'Cloture']" />
    <x-form.textarea name="description" label="Description" col="12" :value="$risque->description ?? ''" :rows="2" required />
    <x-form.select name="probabilite" label="Probabilité" col="3" :value="$risque->probabilite ?? 'moyenne'"
        :options="['faible'=>'Faible','moyenne'=>'Moyenne','elevee'=>'Elevee']" />
    <x-form.select name="impact" label="Impact" col="3" :value="$risque->impact ?? 'moyen'"
        :options="['faible'=>'Faible','moyen'=>'Moyen','eleve'=>'Eleve']" />
    <x-form.select name="responsable_user_id" label="Responsable" col="6" :value="$risque->responsable_user_id ?? ''" placeholder="—">
        @foreach($responsables as $u)
            <option value="{{ $u->id }}" @selected(old('responsable_user_id', $risque->responsable_user_id ?? '')==$u->id)>{{ $u->name }}</option>
        @endforeach
    </x-form.select>
    <x-form.textarea name="mitigation" label="Mesures de mitigation" col="12" :value="$risque->mitigation ?? ''" :rows="2" />
</div>
<x-form.actions :cancel-url="route('strategie.risques.index')" />
