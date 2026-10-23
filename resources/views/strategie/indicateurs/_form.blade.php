<div class="row g-3">
    <x-form.select name="objectif_strategique_id" label="Objectif stratégique" col="6" :value="$indicateur->objectif_strategique_id ?? ''" required>
        @foreach($objectifs as $o)
            <option value="{{ $o->id }}" @selected(old('objectif_strategique_id', $indicateur->objectif_strategique_id ?? '')==$o->id)>{{ $o->code }} — {{ $o->planStrategique?->reference }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="code" label="Code" col="3" :value="$indicateur->code ?? ''" required />
    <x-form.select name="statut" label="Statut" col="3" :value="$indicateur->statut ?? 'actif'"
        :options="['actif'=>'Actif','inactif'=>'Inactif','archive'=>'Archive']" />
    <x-form.input name="libelle" label="Libellé" col="12" :value="$indicateur->libelle ?? ''" required />
    <x-form.input name="unite" label="Unité" col="3" :value="$indicateur->unite ?? ''" />
    <x-form.select name="periodicite" label="Périodicité" col="3" :value="$indicateur->periodicite ?? 'annuel'"
        :options="['annuel'=>'Annuel','semestriel'=>'Semestriel','trimestriel'=>'Trimestriel','mensuel'=>'Mensuel']" />
    <x-form.input name="valeur_reference" label="Référence" type="number" step="any" col="2" :value="$indicateur->valeur_reference ?? ''" />
    <x-form.input name="valeur_cible" label="Cible" type="number" step="any" col="2" :value="$indicateur->valeur_cible ?? ''" />
    <x-form.input name="seuil_alerte" label="Seuil alerte" type="number" step="any" col="2" :value="$indicateur->seuil_alerte ?? ''" />
    <x-form.select name="indicateur_id" label="Indicateur RBM (pont)" col="6" :value="$indicateur->indicateur_id ?? ''" placeholder="—">
        @foreach($indicateursRbm as $r)
            <option value="{{ $r->id }}" @selected(old('indicateur_id', $indicateur->indicateur_id ?? '')==$r->id)>{{ $r->code }} — {{ Str::limit($r->libelle, 40) }}</option>
        @endforeach
    </x-form.select>
</div>
<x-form.actions :cancel-url="route('strategie.indicateurs.index')" />
