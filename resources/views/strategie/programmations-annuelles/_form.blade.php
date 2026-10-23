<div class="row g-3">
    <x-form.select name="programmation_pluriannuelle_id" label="Programmation pluriannuelle" col="6" :value="$programmation->programmation_pluriannuelle_id ?? ''" required>
        @foreach($pluriannuelles as $p)
            <option value="{{ $p->id }}" @selected(old('programmation_pluriannuelle_id', $programmation->programmation_pluriannuelle_id ?? '')==$p->id)>{{ $p->reference }} ({{ $p->annee_debut }}-{{ $p->annee_fin }})</option>
        @endforeach
    </x-form.select>
    <x-form.select name="exercice_id" label="Exercice" col="3" :value="$programmation->exercice_id ?? ''" required>
        @foreach($exercices as $ex)
            <option value="{{ $ex->id }}" @selected(old('exercice_id', $programmation->exercice_id ?? '')==$ex->id)>{{ $ex->annee }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="statut" label="Statut" col="3" :value="$programmation->statut ?? 'brouillon'"
        :options="['brouillon'=>'Brouillon','actif'=>'Actif','archive'=>'Archive']" />
    <x-form.textarea name="activites_prevues" label="Activités prévues" col="12" :value="$programmation->activites_prevues ?? ''" :rows="2" />
    <x-form.textarea name="ressources_prevues" label="Ressources prévues" col="6" :value="$programmation->ressources_prevues ?? ''" :rows="2" />
    <x-form.textarea name="indicateurs_prevus" label="Indicateurs prévus" col="6" :value="$programmation->indicateurs_prevus ?? ''" :rows="2" />
</div>
<x-form.actions :cancel-url="route('strategie.programmations-annuelles.index')" />
