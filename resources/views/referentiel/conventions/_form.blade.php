@csrf
@php $c = $convention ?? null; @endphp
<input type="hidden" name="partenaire_id" value="{{ old('partenaire_id', $c->partenaire_id ?? $partenaire->id ?? '') }}">
<div class="row g-3">
    <x-form.input name="code" label="Code" col="3" :value="$c->code ?? ''" required />
    <x-form.input name="libelle" label="Libellé" col="9" :value="$c->libelle ?? ''" required />
    <x-form.input name="reference_accord" label="Réf. accord" col="4" :value="$c->reference_accord ?? ''" />
    <x-form.select name="source_financement_id" label="Source financement" col="4" :value="$c->source_financement_id ?? ''" placeholder="— Aucune —">
        @foreach ($sources as $s)
            <option value="{{ $s->id }}" @selected(old('source_financement_id', $c->source_financement_id ?? '')==$s->id)>{{ $s->code }} · {{ $s->libelle }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="exercice_id" label="Exercice" col="4" :value="$c->exercice_id ?? ''" placeholder="— Tous —">
        @foreach ($exercices as $e)
            <option value="{{ $e->id }}" @selected(old('exercice_id', $c->exercice_id ?? '')==$e->id)>{{ $e->annee }} · {{ $e->libelle }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="date_signature" label="Date signature" type="date" col="3" :value="optional($c->date_signature)->format('Y-m-d')" />
    <x-form.input name="date_debut" label="Date début" type="date" col="3" :value="optional($c->date_debut)->format('Y-m-d')" />
    <x-form.input name="date_fin" label="Date fin" type="date" col="3" :value="optional($c->date_fin)->format('Y-m-d')" />
    <x-form.select name="statut" label="Statut" col="3" :value="$c->statut ?? 'brouillon'" required :options="\App\Models\ConventionFinancement::STATUTS" />
    <x-form.input name="montant_accord" label="Montant accord" col="3" :value="$c->montant_accord ?? 0" required />
    <x-form.input name="montant_engage" label="Montant engagé" col="3" :value="$c->montant_engage ?? 0" />
    <x-form.input name="montant_execute" label="Montant exécuté" col="3" :value="$c->montant_execute ?? 0" />
    <x-form.input name="montant_decaisse" label="Montant décaissé" col="3" :value="$c->montant_decaisse ?? 0" />
</div>
<x-form.actions :cancel-url="route('partenaires.show', $partenaire)" />
