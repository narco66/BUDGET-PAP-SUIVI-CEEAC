@csrf
<div class="row g-3">
    <x-form.input name="code" label="Code" col="3" :value="$exercice->code ?? ''" required />
    <x-form.input name="annee" label="Année" type="number" col="3" :value="$exercice->annee ?? date('Y')" min="2000" max="2100" required />
    <x-form.input name="libelle" label="Libellé" col="6" :value="$exercice->libelle ?? ''" required />
    <x-form.input name="date_debut" label="Date début" type="date" col="3" :value="optional($exercice->date_debut)->format('Y-m-d')" required />
    <x-form.input name="date_fin" label="Date fin" type="date" col="3" :value="optional($exercice->date_fin)->format('Y-m-d')" required />
    <x-form.input name="devise" label="Devise" col="3" :value="$exercice->devise ?? 'XAF'" maxlength="3" required />
    <x-form.input name="taux_change_usd" label="Taux change USD" type="number" col="3" step="0.000001" :value="$exercice->taux_change_usd" />
</div>
<x-form.actions :cancel-url="route('exercices.index')" />
