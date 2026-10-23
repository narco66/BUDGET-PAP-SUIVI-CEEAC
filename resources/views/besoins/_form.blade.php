@csrf
@php
    $b = $besoin ?? null;
    $selectedActiviteId = (string) old('activite_id', $b->activite_id ?? '');
    $selectedTacheId = (string) old('tache_id', $b->tache_id ?? '');
@endphp

<div class="row g-3">
    <x-form.select name="exercice_id" label="Exercice" col="3" :value="$b->exercice_id ?? ''" required placeholder="— Choisir —">
        @foreach ($exercices as $ex)
            <option value="{{ $ex->id }}" @selected(old('exercice_id', $b->exercice_id ?? '') == $ex->id || (! $b && $ex->statut === 'ouvert'))>{{ $ex->annee }}</option>
        @endforeach
    </x-form.select>

    <x-form.input name="objet" label="Objet" col="9" :value="$b->objet ?? ''" required />

    <x-form.select name="structure_id" label="Structure demandeuse" col="6" id="structure_id" :value="$b->structure_id ?? ''" placeholder="— Non défini —">
        @foreach ($structures as $s)
            <option value="{{ $s->id }}" @selected(old('structure_id', $b->structure_id ?? '') == $s->id)>{{ $s->code }} · {{ $s->libelle }}</option>
        @endforeach
    </x-form.select>

    <div class="col-md-6">
        <label for="activite_search" class="form-label">Recherche activité</label>
        <input type="search" id="activite_search" class="form-control" placeholder="Rechercher par code ou libellé">
        <div class="form-text">Filtre la liste des activités accessibles.</div>
    </div>

    <x-form.select name="activite_id" label="Activité rattachée" col="6" id="activite_id" :value="$b->activite_id ?? ''" required>
        @foreach ($activites as $a)
            @php $label = $a->code.' · '.\Illuminate\Support\Str::limit($a->intitule, 80); @endphp
            <option
                value="{{ $a->id }}"
                data-search="{{ \Illuminate\Support\Str::lower($a->code.' '.$a->intitule) }}"
                @selected(old('activite_id', $b->activite_id ?? '') == $a->id)
            >{{ $label }}</option>
        @endforeach
    </x-form.select>

    <x-form.select name="tache_id" label="Tâche PAP" col="6" id="tache_id" :value="$b->tache_id ?? ''" placeholder="— Non définie —">
        @foreach ($taches as $t)
            <option
                value="{{ $t->id }}"
                data-activite="{{ $t->activite_id }}"
                @selected(old('tache_id', $b->tache_id ?? '') == $t->id)
            >{{ $t->code }} · {{ \Illuminate\Support\Str::limit($t->intitule, 80) }}</option>
        @endforeach
    </x-form.select>

    <x-form.select name="priorite" label="Priorité" col="3" :value="$b->priorite ?? 'moyenne'" :options="$priorites" />

    <x-form.input name="montant_estime" label="Montant estimé (XAF)" type="number" step="0.01" col="3" :value="$b->montant_estime ?? 0" required />

    <x-form.input name="date_besoin" label="Date du besoin" type="date" col="4" :value="$b?->date_besoin?->format('Y-m-d')" />

    <x-form.textarea name="justification" label="Justification" col="12" :value="$b->justification ?? ''" :rows="3" />
</div>

<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('activite_search');
    const activiteSelect = document.getElementById('activite_id');
    const tacheSelect = document.getElementById('tache_id');

    function filterTasks() {
        const activiteId = activiteSelect?.value || '';
        let selectedStillVisible = !tacheSelect.value;

        tacheSelect.querySelectorAll('option[data-activite]').forEach(option => {
            option.hidden = !!activiteId && option.dataset.activite !== activiteId;
            if (option.selected && !option.hidden) {
                selectedStillVisible = true;
            }
        });

        if (!selectedStillVisible) {
            tacheSelect.value = '';
        }
    }

    function filterActivities() {
        const search = (searchInput?.value || '').trim().toLowerCase();
        let selectedStillVisible = !activiteSelect.value;

        activiteSelect.querySelectorAll('option[data-search]').forEach(option => {
            option.hidden = !!search && !option.dataset.search.includes(search);
            if (option.selected && !option.hidden) {
                selectedStillVisible = true;
            }
        });

        if (!selectedStillVisible) {
            activiteSelect.value = '';
        }

        filterTasks();
    }

    searchInput?.addEventListener('input', filterActivities);
    activiteSelect?.addEventListener('change', filterTasks);

    filterActivities();
});
</script>

<x-form.actions :cancel-url="route('besoins.index')" />
