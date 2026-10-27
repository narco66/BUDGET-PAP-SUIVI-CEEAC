@csrf
@php
    $b = $besoin ?? null;
    // « Activité rattachée » : une activité du PAP (valeur = identifiant de l'activité), une ligne à 5 chiffres du
    // budget de fonctionnement (« D{id} ») ou une ligne d'équipement (« L{id} ») ; ces deux dernières = besoin hors PAP.
    $selectedImputation = (string) old('activite_id', $b
        ? (($b->categorie ?? 'pap') === 'hors_pap'
            ? ($b->ligne_detail_id ? 'D'.$b->ligne_detail_id : ($b->ligne_budgetaire_id ? 'L'.$b->ligne_budgetaire_id : ''))
            : ($b->activite_id ?? ''))
        : '');
    $selectedTacheId = (string) old('tache_id', $b->tache_id ?? '');
    $fmt = fn ($n) => $n === null ? '—' : number_format((float) $n, 0, ',', ' ');
    // Année de l'exercice du dossier (ou de l'exercice actif), lue en base.
    $anneeImputation = ($b?->exercice_id ? \App\Models\Exercice::find($b->exercice_id)?->annee : null) ?? \App\Models\Exercice::courant()?->annee;
    $groupes = [
        ['Investissement — Plan annuel de performance '.$anneeImputation, $imputations['pap'], 'pap'],
        ['Fonctionnement — Budget '.$anneeImputation, $imputations['fonctionnement'], 'fonctionnement'],
        ['Équipements, assises et dotations — Budget '.$anneeImputation, $imputations['equipement'], 'equipement'],
    ];
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
        <input type="search" id="activite_search" class="form-control" placeholder="Rechercher par code ou libellé (ex. 206323, 6111, voyages)">
        <div class="form-text" id="activite_compteur">Filtre les activités du PAP et les lignes du budget.</div>
    </div>

    <x-form.select name="activite_id" label="Activité rattachée" col="6" id="activite_id" :value="$selectedImputation" required placeholder="— Choisir l'activité ou la ligne budgétaire —">
        @foreach ($groupes as [$libelleGroupe, $options, $volet])
            <optgroup label="{{ $libelleGroupe }}">
                @foreach ($options as $o)
                    @php $valeur = match ($volet) { 'pap' => (string) $o['activite_id'], 'fonctionnement' => 'D'.$o['detail_id'], default => 'L'.$o['ligne_id'] }; @endphp
                    <option
                        value="{{ $valeur }}"
                        data-search="{{ \Illuminate\Support\Str::lower($o['code'].' '.$o['libelle']) }}"
                        data-volet="{{ $volet }}"
                        data-exercice="{{ $o['exercice_id'] }}"
                        data-enveloppe="{{ $o['enveloppe'] ?? '' }}"
                        @if ($volet === 'pap') data-total="{{ $o['total'] }}" data-nb-taches="{{ $o['nb_taches'] }}" data-total-taches="{{ $o['total_taches'] }}"
                            data-chaine="{{ $o['chaine'] }}" data-responsable="{{ $o['responsable'] }}" @endif
                        @selected($selectedImputation === $valeur)
                    >{{ $o['code'] }} — {{ $o['libelle'] }} · {{ $fmt($o['disponible']) }} XAF</option>
                @endforeach
            </optgroup>
        @endforeach
    </x-form.select>

    <x-form.select name="tache_id" label="Tâche PAP" col="6" id="tache_id" :value="$b->tache_id ?? ''" placeholder="— Non définie —">
        @foreach ($taches as $t)
            <option
                value="{{ $t->id }}"
                data-activite="{{ $t->activite_id }}"
                @selected(old('tache_id', $b->tache_id ?? '') == $t->id)
            >{{ $t->code }} · {{ \Illuminate\Support\Str::limit($t->intitule, 80) }} · {{ $fmt($t->montant) }} XAF</option>
        @endforeach
    </x-form.select>

    <div class="col-12">
        <div id="imputation_aide" class="form-text"></div>
    </div>

    {{-- Chaîne RBM de la ligne PAP et responsable (à renseigner ici s'il manque dans le référentiel). --}}
    <div class="col-12" id="bloc_rbm" hidden>
        <div class="small"><i class="bi bi-diagram-3 me-1"></i><span class="text-muted">Chaîne RBM :</span> <span id="rbm_chaine"></span></div>
        <div class="small mt-1" id="rbm_responsable_connu"><i class="bi bi-person-check me-1"></i><span class="text-muted">Responsable de l'activité :</span> <strong id="rbm_responsable"></strong></div>
    </div>
    <div class="col-md-6" id="bloc_responsable_activite" hidden>
        <label for="activite_responsable_id" class="form-label">Responsable de l'activité <span class="text-danger">*</span></label>
        <select name="activite_responsable_id" id="activite_responsable_id" class="form-select @error('activite_responsable_id') is-invalid @enderror">
            <option value="">— Choisir le responsable —</option>
            @foreach ($responsables as $u)
                <option value="{{ $u->id }}" @selected((string) old('activite_responsable_id') === (string) $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <div class="form-text">Aucun responsable n'est enregistré pour cette activité dans le PAP : il le sera à l'enregistrement.</div>
        @error('activite_responsable_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>


    <x-form.select name="priorite" label="Priorité" col="3" :value="$b->priorite ?? 'moyenne'" :options="$priorites" />

    <x-form.input name="montant_estime" label="Montant estimé (XAF)" type="number" step="0.01" col="3" :value="$b->montant_estime ?? 0" required />

    <x-form.input name="date_besoin" label="Date du besoin" type="date" col="4" :value="$b?->date_besoin?->format('Y-m-d')" />

    <x-form.textarea name="justification" label="Justification" col="12" :value="$b->justification ?? ''" :rows="3" />

    {{-- Tâches de l'activité (sous-lignes de la ligne PAP), placées après la justification : leur total égale le
         montant estimé ; le cumul des tâches de la ligne ne dépasse pas son total. --}}
    @php
        // Tâches de l'activité : saisie en cours, sinon celles déjà rattachées au besoin (modification).
        $tachesBesoin = $b?->tachesActivite ?? collect();
        $sousLignes = array_values((array) old('taches_nouvelles', $tachesBesoin->map(fn ($t) => [
            'intitule' => $t->intitule, 'montant' => (float) $t->montant, 'responsable_user_id' => $t->responsable_user_id,
        ])->all()));
    @endphp
    <div class="col-12" id="bloc_taches" data-taches-besoin="{{ (float) $tachesBesoin->sum('montant') }}" hidden>
        <div class="card border-0 bg-light">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <strong class="small"><i class="bi bi-list-task me-1"></i>Tâches de la ligne PAP</strong>
                    <span class="small" id="taches_resume"></span>
                </div>
                <div id="taches_saisie" hidden>
                    <p class="small text-muted mb-2">Une ligne PAP peut faire l'objet de plusieurs activités. Saisissez les tâches de l'activité exprimée par ce besoin : leur total doit être égal au montant de l'activité (montant estimé, calculé automatiquement), et le cumul des tâches de la ligne ne peut pas dépasser son total. Cochez, le cas échéant, la tâche concernée par ce besoin.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-2">
                            <thead><tr><th style="width:60px" class="text-center">Concernée</th><th>Intitulé de la tâche</th><th style="width:220px">Responsable</th><th style="width:180px" class="text-end">Montant (XAF)</th><th style="width:40px"></th></tr></thead>
                            <tbody id="taches_lignes">
                                @foreach ($sousLignes as $i => $l)
                                    <tr>
                                        <td class="text-center"><input type="radio" class="form-check-input" name="tache_nouvelle_concernee" value="{{ $i }}" @checked((string) old('tache_nouvelle_concernee') === (string) $i)></td>
                                        <td><input type="text" class="form-control form-control-sm" name="taches_nouvelles[{{ $i }}][intitule]" value="{{ $l['intitule'] ?? '' }}" required maxlength="1000"></td>
                                        <td><select class="form-select form-select-sm" name="taches_nouvelles[{{ $i }}][responsable_user_id]" required>
                                            <option value="">— Responsable —</option>
                                            @foreach ($responsables as $u)<option value="{{ $u->id }}" @selected((string) ($l['responsable_user_id'] ?? '') === (string) $u->id)>{{ $u->name }}</option>@endforeach
                                        </select></td>
                                        <td><input type="number" class="form-control form-control-sm text-end js-montant-tache" name="taches_nouvelles[{{ $i }}][montant]" value="{{ $l['montant'] ?? '' }}" min="0" step="1" required></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger js-retirer-tache" title="Retirer"><i class="bi bi-x-lg"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><td></td><td colspan="2" class="text-end small">Total des tâches de l'activité</td><td class="text-end small fw-semibold" id="taches_total"></td><td></td></tr></tfoot>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="ajouter_tache"><i class="bi bi-plus-lg me-1"></i>Ajouter une tâche</button>
                </div>
                @error('taches_nouvelles')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('tache_nouvelle_concernee')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <template id="modele_tache">
        <tr>
            <td class="text-center"><input type="radio" class="form-check-input" name="tache_nouvelle_concernee" value="__I__"></td>
            <td><input type="text" class="form-control form-control-sm" name="taches_nouvelles[__I__][intitule]" required maxlength="1000"></td>
            <td><select class="form-select form-select-sm" name="taches_nouvelles[__I__][responsable_user_id]" required>
                <option value="">— Responsable —</option>
                @foreach ($responsables as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
            </select></td>
            <td><input type="number" class="form-control form-control-sm text-end js-montant-tache" name="taches_nouvelles[__I__][montant]" min="0" step="1" required></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger js-retirer-tache" title="Retirer"><i class="bi bi-x-lg"></i></button></td>
        </tr>
    </template>
</div>

<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('activite_search');
    const activiteSelect = document.getElementById('activite_id');
    const tacheSelect = document.getElementById('tache_id');
    const exerciceSelect = document.querySelector('[name="exercice_id"]');
    const aide = document.getElementById('imputation_aide');

    function filterTasks() {
        const option = activiteSelect?.selectedOptions[0];
        const activiteId = option?.dataset.volet === 'pap' ? activiteSelect.value : '';
        let selectedStillVisible = !tacheSelect.value;

        tacheSelect.disabled = !!option?.dataset.volet && option.dataset.volet !== 'pap';
        tacheSelect.querySelectorAll('option[data-activite]').forEach(o => {
            o.hidden = !activiteId || o.dataset.activite !== activiteId;
            if (o.selected && !o.hidden) {
                selectedStillVisible = true;
            }
        });

        if (!selectedStillVisible || tacheSelect.disabled) {
            tacheSelect.value = '';
        }
    }

    function afficherImputation() {
        const option = activiteSelect?.selectedOptions[0];
        aide.textContent = !option?.dataset.volet ? ''
            : option.dataset.volet === 'pap'
                ? 'Besoin PAP — imputation budgétaire : ' + (option.dataset.enveloppe || 'enveloppe de l’axe') + '.'
                : 'Besoin hors PAP (budget de fonctionnement ou d’équipement) — imputation sur la ligne choisie ; la justification est obligatoire.';
    }

    // Recherche textuelle et exercice choisi ; les activités du PAP sans exercice d'imputation restent visibles.
    // Recherche : la liste est reconstruite à partir d'une copie complète (l'attribut « hidden » sur <option>
    // n'est pas appliqué par tous les navigateurs) ; comparaison sans accents ni casse, sur tous les mots saisis.
    const sansAccents = t => (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const placeholder = activiteSelect.querySelector('option:not([data-search])')?.cloneNode(true);
    const groupesComplets = [...activiteSelect.querySelectorAll('optgroup')].map(g => g.cloneNode(true));
    const compteur = document.getElementById('activite_compteur');

    function filterActivities() {
        const mots = sansAccents(searchInput?.value).split(/\s+/).filter(Boolean);
        const exerciceId = exerciceSelect?.value || '';
        const choisi = activiteSelect.value;
        let trouves = 0;

        activiteSelect.replaceChildren(...(placeholder ? [placeholder.cloneNode(true)] : []));
        groupesComplets.forEach(groupe => {
            const copie = groupe.cloneNode(false);
            groupe.querySelectorAll('option').forEach(o => {
                const texte = sansAccents(o.dataset.search + ' ' + o.textContent);
                const visible = mots.every(m => texte.includes(m))
                    && !(exerciceId && o.dataset.exercice && o.dataset.exercice !== exerciceId);
                if (visible) {
                    copie.appendChild(o.cloneNode(true));
                    trouves++;
                }
            });
            if (copie.children.length) activiteSelect.appendChild(copie);
        });

        // Conserve le choix s'il figure toujours dans la liste ; sélectionne d'office un résultat unique.
        const options = [...activiteSelect.querySelectorAll('option[data-search]')];
        if (options.some(o => o.value === choisi)) {
            activiteSelect.value = choisi;
        } else if (mots.length && options.length === 1) {
            activiteSelect.value = options[0].value;
        } else {
            activiteSelect.value = '';
        }
        compteur.textContent = mots.length ? trouves + ' résultat(s) pour « ' + searchInput.value.trim() + ' »' : 'Filtre les activités du PAP et les lignes du budget.';

        filterTasks();
        afficherImputation();
        if (typeof afficherTaches === 'function' && blocTaches) afficherTaches();
    }

    // Tâches de la ligne PAP : résumé si elle en a, saisie en sous-lignes sinon (total des tâches = total de la ligne).
    const blocTaches = document.getElementById('bloc_taches');
    const saisie = document.getElementById('taches_saisie');
    const corps = document.getElementById('taches_lignes');
    const resume = document.getElementById('taches_resume');
    const totalCell = document.getElementById('taches_total');
    const modele = document.getElementById('modele_tache');
    const fmt = n => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n);

    function renumeroter() {
        [...corps.rows].forEach((tr, i) => {
            tr.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(/taches_nouvelles\[\d+\]/, 'taches_nouvelles[' + i + ']');
                if (el.type === 'radio') el.value = i;
            });
        });
    }

    const montantEstime = document.querySelector('[name="montant_estime"]');

    // Total des tâches de l'activité = montant estimé (synchronisé tant que des tâches sont saisies).
    function majTotal() {
        const lignes = corps.rows.length;
        const somme = [...corps.querySelectorAll('.js-montant-tache')].reduce((s, el) => s + (parseFloat(el.value) || 0), 0);
        totalCell.textContent = fmt(somme) + ' XAF';
        if (montantEstime) {
            montantEstime.readOnly = lignes > 0;
            if (lignes > 0) montantEstime.value = somme;
        }
        tacheSelect.disabled = lignes > 0 || tacheSelect.dataset.horsPap === '1';
        if (lignes > 0) tacheSelect.value = '';

        const option = activiteSelect?.selectedOptions[0];
        const totalLigne = parseFloat(option?.dataset.total || '0');
        const autres = parseFloat(option?.dataset.totalTaches || '0') - parseFloat(blocTaches.dataset.tachesBesoin || '0');
        const reste = totalLigne - autres - somme;
        totalCell.className = 'text-end small fw-semibold ' + (reste < -0.5 ? 'text-danger' : 'text-success');
        resume.textContent = 'Ligne : ' + fmt(totalLigne) + ' XAF · tâches des autres activités : ' + fmt(Math.max(0, autres))
            + ' XAF · reste disponible sur la ligne : ' + fmt(reste) + ' XAF';
    }

    function afficherTaches() {
        const option = activiteSelect?.selectedOptions[0];
        const pap = option?.dataset.volet === 'pap';
        blocTaches.hidden = !pap;
        saisie.hidden = !pap;
        tacheSelect.dataset.horsPap = pap ? '0' : '1';
        // Les tâches ne sont transmises que pour une ligne PAP.
        corps.querySelectorAll('input, select').forEach(el => { el.disabled = !pap; });

        // Chaîne RBM et responsable de la ligne PAP.
        document.getElementById('bloc_rbm').hidden = !pap;
        const responsable = option?.dataset.responsable || '';
        document.getElementById('rbm_chaine').textContent = option?.dataset.chaine || '—';
        document.getElementById('rbm_responsable').textContent = responsable;
        document.getElementById('rbm_responsable_connu').hidden = !responsable;
        const blocResp = document.getElementById('bloc_responsable_activite');
        blocResp.hidden = !pap || !!responsable;
        const selectResp = document.getElementById('activite_responsable_id');
        selectResp.disabled = blocResp.hidden;
        selectResp.required = !blocResp.hidden;
        if (pap) {
            majTotal();
        } else if (montantEstime) {
            montantEstime.readOnly = false;
        }
    }

    document.getElementById('ajouter_tache')?.addEventListener('click', () => {
        corps.insertAdjacentHTML('beforeend', modele.innerHTML.replaceAll('__I__', corps.rows.length));
        renumeroter();
        majTotal();
        corps.rows[corps.rows.length - 1].querySelector('input[type=text]').focus();
    });
    corps.addEventListener('click', e => {
        if (e.target.closest('.js-retirer-tache')) {
            e.target.closest('tr').remove();
            renumeroter();
            majTotal();
        }
    });
    corps.addEventListener('input', majTotal);

    searchInput?.addEventListener('input', filterActivities);
    exerciceSelect?.addEventListener('change', filterActivities);
    activiteSelect?.addEventListener('change', () => { filterTasks(); afficherImputation(); afficherTaches(); });

    filterActivities();
    afficherTaches();
});
</script>

<x-form.actions :cancel-url="route('besoins.index')" />
