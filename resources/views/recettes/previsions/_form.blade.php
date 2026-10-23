@csrf
<div class="row g-3">
    <x-form.select name="exercice_id" label="Exercice" col="4" :value="$prevision->exercice_id" required placeholder="—">
        @foreach ($exercices as $ex)
            <option value="{{ $ex->id }}" @selected(old('exercice_id', $prevision->exercice_id)==$ex->id)>Exercice {{ $ex->annee }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="reference" label="Référence" col="4" :value="$prevision->reference" maxlength="60" required />
    <x-form.input name="scenario" label="Scénario" col="4" :value="$prevision->scenario ?? 'initial'" maxlength="30" />
    <x-form.select name="categorie_recette_id" label="Catégorie de recette" col="6" :value="$prevision->categorie_recette_id" placeholder="—">
        @foreach ($categories as $cat)
            <option value="{{ $cat->id }}" @selected(old('categorie_recette_id', $prevision->categorie_recette_id)==$cat->id)>{{ $cat->libelle }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="montant_prevu" label="Montant prévu (XAF)" type="number" step="0.01" min="0" col="6" :value="$prevision->montant_prevu" required />
    <x-form.select name="etat_membre_id" label="État membre" col="4" :value="$prevision->etat_membre_id" placeholder="—">
        @foreach ($etats as $etat)
            <option value="{{ $etat->id }}" @selected(old('etat_membre_id', $prevision->etat_membre_id)==$etat->id)>{{ $etat->pays }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="source_financement_id" label="Source / PTF" col="4" :value="$prevision->source_financement_id" placeholder="—">
        @foreach ($sources as $src)
            <option value="{{ $src->id }}" @selected(old('source_financement_id', $prevision->source_financement_id)==$src->id)>{{ $src->libelle }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="rubrique_id" label="Imputation (rubrique)" col="4" :value="$prevision->rubrique_id" placeholder="—">
        @foreach ($rubriques as $rub)
            <option value="{{ $rub->id }}" @selected(old('rubrique_id', $prevision->rubrique_id)==$rub->id)>{{ $rub->code }} — {{ \Illuminate\Support\Str::limit($rub->intitule, 40) }}</option>
        @endforeach
    </x-form.select>
    <x-form.textarea name="observations" label="Observations" col="12" :value="$prevision->observations" :rows="2" />
</div>
