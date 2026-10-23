<div class="row g-3">
    <x-form.input name="reference" label="Référence" col="3" :value="$vision->reference ?? ''" required />
    <x-form.input name="intitule" label="Intitulé" col="5" :value="$vision->intitule ?? ''" required />
    <x-form.input name="periode" label="Période" col="2" :value="$vision->periode ?? ''" placeholder="2025-2035" />
    <x-form.input name="version" label="Version" type="number" col="2" min="1" :value="$vision->version ?? 1" />
    <x-form.input name="date_debut" label="Date début" type="date" col="3" :value="optional($vision->date_debut)->format('Y-m-d')" />
    <x-form.input name="date_fin" label="Date fin" type="date" col="3" :value="optional($vision->date_fin)->format('Y-m-d')" />
    <x-form.select name="statut" label="Statut" col="3" :value="$vision->statut ?? 'brouillon'" required
        :options="['brouillon'=>'Brouillon','actif'=>'Actif','archive'=>'Archive','obsolete'=>'Obsolete']" />
    <x-form.input name="document_officiel" label="Document officiel" col="3" :value="$vision->document_officiel ?? ''" />
    <x-form.textarea name="description" label="Description" col="12" :value="$vision->description ?? ''" :rows="3" />
</div>
<x-form.actions :cancel-url="route('strategie.visions.index')" />
