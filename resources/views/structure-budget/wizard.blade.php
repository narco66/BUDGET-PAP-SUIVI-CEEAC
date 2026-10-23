@extends('layouts.app')
@section('titre', 'Wizard — Structure du budget')

@push('styles')<style @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
    .sb-wizard-steps { display:flex; gap:.25rem; flex-wrap:wrap; margin-bottom:1.25rem; }
    .sb-wizard-steps .step { flex:1; min-width:5rem; text-align:center; padding:.45rem .35rem; border-radius:.35rem;
        background:#eef3f9; color:#6c757d; font-size:.72rem; font-weight:600; border:2px solid transparent; }
    .sb-wizard-steps .step.active { background:#0a4a8f; color:#fff; }
    .sb-wizard-steps .step.done { background:#d1e7dd; color:#0f5132; border-color:#badbcc; }
    .sb-pane { display:none; } .sb-pane.active { display:block; }
    .sb-code { font-family:monospace; color:#0a4a8f; }
    .sb-recap dt { font-weight:600; color:#6c757d; }
    #alertes-box .alert { font-size:.875rem; padding:.5rem .75rem; margin-bottom:.35rem; }
</style>@endpush

@section('contenu')
<a href="{{ route('structure-budget.arbre', ['exercice' => $exercice?->id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Structure du budget</a>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
    <div>
        <h4 class="fw-bold mb-0">Assistant de saisie — wizard</h4>
        <span class="text-muted small">Exercice → Structure → Activité PAP → Nomenclature → Financement → Montant</span>
    </div>
</div>

<div class="sb-wizard-steps" id="steps-bar">
    @foreach (['Exercice', 'Structure', 'Activité PAP', 'Nomenclature', 'Financement', 'Validation'] as $i => $label)
        <div class="step {{ $i === 0 ? 'active' : '' }}" data-step="{{ $i }}">{{ $i + 1 }}. {{ $label }}</div>
    @endforeach
</div>
<div id="wizard-step-error" class="alert alert-danger d-none mb-2" role="alert"></div>

<form method="POST" action="{{ route('structure-budget.wizard.store') }}" id="wizard-form">
    @csrf
    <input type="hidden" name="exercice_id" id="exercice_id" value="{{ $exercice?->id }}">

    {{-- Étape 0 : Exercice --}}
    <div class="sb-pane active" data-pane="0">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <label class="form-label">Exercice budgétaire *</label>
            <select id="sel-exercice" class="form-select">
                @foreach ($exercices as $ex)
                    <option value="{{ $ex->id }}" data-annee="{{ $ex->annee }}" data-statut="{{ $ex->statut }}"
                        @selected($exercice?->id === $ex->id)>{{ $ex->annee }} — {{ $ex->libelle }} ({{ $ex->statut }})</option>
                @endforeach
            </select>
            <div id="exercice-info" class="form-text mt-2"></div>
        </div></div>
    </div>

    {{-- Étape 1 : Structure --}}
    <div class="sb-pane" data-pane="1">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <x-form.select name="structure_id" id="structure_id" label="Structure de rattachement" :value="request('structure')" required>
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected((int) request('structure') === $s->id)>
                        {{ $s->code }} · {{ $s->sigle ?: $s->libelle }} ({{ str_replace('_', ' ', $s->type) }})
                    </option>
                @endforeach
            </x-form.select>
            <div id="fil-structure" class="mt-2 small text-muted"></div>
        </div></div>
    </div>

    {{-- Étape 2 : Activité PAP --}}
    <div class="sb-pane" data-pane="2">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <x-form.select name="activite_id" id="activite_id" label="Activité PAP (optionnelle)" :value="old('activite_id')" placeholder="— Aucune / hors PAP —"
                help="Activités filtrées par exercice et structure. Cohérence PAP contrôlée à la validation." />
            <div id="activite-detail" class="small mt-2 text-muted"></div>
        </div></div>
    </div>

    {{-- Étape 3 : Nomenclature --}}
    <div class="sb-pane" data-pane="3">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <x-form.select name="rubrique_id" id="rubrique_id" label="Rubrique budgétaire" :value="old('rubrique_id')" required>
                @foreach ($rubriques as $r)
                    <option value="{{ $r->id }}" data-statut="{{ $r->statut ?? 'valide' }}">
                        {{ $r->code }} · {{ \Illuminate\Support\Str::limit($r->intitule, 50) }}
                    </option>
                @endforeach
            </x-form.select>
            <div id="chemin-nomenclature" class="mt-2 small"></div>
        </div></div>
    </div>

    {{-- Étape 4 : Financement --}}
    <div class="sb-pane" data-pane="4">
        <div class="card border-0 shadow-sm"><div class="card-body row g-3">
            <div class="col-md-6">
                <label for="filtre_partenaire" class="form-label">Partenaire (filtre)</label>
                <select id="filtre_partenaire" class="form-select">
                    <option value="">— Tous —</option>
                    @foreach ($partenaires as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} · {{ $p->sigle ?: $p->libelle }}</option>
                    @endforeach
                </select>
            </div>
            <x-form.select name="source_financement_id" id="source_financement_id" label="Source de financement" col="6" :value="old('source_financement_id')" placeholder="— Aucune —">
                @foreach ($sources as $sf)
                    <option value="{{ $sf->id }}" data-partenaire="{{ $sf->partenaire_id }}">{{ $sf->code }} · {{ $sf->libelle }}</option>
                @endforeach
            </x-form.select>
        </div></div>
    </div>

    {{-- Étape 5 : Montant & validation --}}
    <div class="sb-pane" data-pane="5">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm"><div class="card-body row g-3">
                    <x-form.input name="code" id="code" label="Code ligne" col="6" :value="old('code', $codeSuggere)" required input-class="sb-code" />
                    <x-form.input name="montant_ouvert" id="montant_ouvert" label="Montant ouvert (XAF)" type="number" col="6" min="0" step="0.01" :value="old('montant_ouvert', 0)" required />
                    <x-form.input name="intitule" id="intitule" label="Intitulé" col="12" :value="old('intitule')" maxlength="255" />
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Récapitulatif</div>
                    <div class="card-body"><dl class="row mb-0 small" id="recap"></dl></div>
                </div>
                <div id="synthese-pap" class="card border-0 shadow-sm mt-2 d-none">
                    <div class="card-header bg-white fw-semibold py-2"><i class="bi bi-calculator me-1"></i>Synthèse budgétaire — activité PAP</div>
                    <div class="card-body py-2" id="synthese-pap-body"></div>
                </div>
                <div id="alertes-box" class="mt-2"></div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-light" id="btn-prev" disabled><i class="bi bi-arrow-left me-1"></i>Précédent</button>
        <button type="button" class="btn btn-primary" id="btn-next">Suivant<i class="bi bi-arrow-right ms-1"></i></button>
        <button type="submit" class="btn btn-success d-none" id="btn-submit"><i class="bi bi-check-lg me-1"></i>Créer la ligne budgétaire</button>
    </div>
</form>
@endsection

@push('scripts')<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
(function () {
    const URL_CTX = "{{ route('structure-budget.wizard.contexte') }}";
    const URL_ACT = "{{ route('structure-budget.wizard.activites') }}";
    const URL_RUB = @json(url('structure-budget/wizard/rubrique'));
    const URL_VER = "{{ route('structure-budget.wizard.verifier') }}";

    let step = 0, maxStep = 5, exerciceModifiable = true, codeEdite = false;
    const fmt = n => Number(n || 0).toLocaleString('fr-FR');
    const panes = document.querySelectorAll('.sb-pane');
    const steps = document.querySelectorAll('.sb-wizard-steps .step');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    const btnSubmit = document.getElementById('btn-submit');
    const selExercice = document.getElementById('sel-exercice');
    const hidExercice = document.getElementById('exercice_id');

    function showStep(n) {
        step = n;
        panes.forEach(p => p.classList.toggle('active', +p.dataset.pane === step));
        steps.forEach(s => {
            const i = +s.dataset.step;
            s.classList.toggle('active', i === step);
            s.classList.toggle('done', i < step);
        });
        btnPrev.disabled = step === 0;
        btnNext.classList.toggle('d-none', step === maxStep);
        btnSubmit.classList.toggle('d-none', step !== maxStep);
        if (step === maxStep) { buildRecap(); runVerifier(); }
    }

    async function loadContexte() {
        const exId = selExercice.value;
        hidExercice.value = exId;
        const stId = document.getElementById('structure_id').value;
        const q = new URLSearchParams({ exercice_id: exId });
        if (stId) q.set('structure_id', stId);
        const r = await fetch(`${URL_CTX}?${q}`, { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const d = await r.json();
        exerciceModifiable = d.exercice.modifiable;
        document.getElementById('exercice-info').innerHTML = d.exercice.modifiable
            ? `<span class="text-success">Exercice modifiable.</span>`
            : `<span class="text-danger">${d.exercice.message_verrou || 'Exercice verrouillé.'}</span>`;
        if (d.code_suggere && !codeEdite) document.getElementById('code').value = d.code_suggere;
        if (d.structure) {
            document.getElementById('fil-structure').innerHTML = d.structure.fil
                .map(f => `<span class="badge bg-light text-dark me-1">${f.type}: ${f.code}</span>`).join(' → ');
        }
    }

    async function loadActivites() {
        const exId = hidExercice.value, stId = document.getElementById('structure_id').value;
        const sel = document.getElementById('activite_id');
        sel.innerHTML = '<option value="">— Aucune / hors PAP —</option>';
        document.getElementById('activite-detail').textContent = '';
        if (!stId) return;
        const r = await fetch(`${URL_ACT}?exercice_id=${exId}&structure_id=${stId}`, { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const d = await r.json();
        (d.activites || []).forEach(a => {
            const o = document.createElement('option');
            o.value = a.id;
            o.textContent = `${a.code} · ${a.intitule}${a.a_ligne ? ' (liée: ' + a.ligne_code + ')' : ''}`;
            o.dataset.pilier = a.pilier || '';
            o.dataset.montantTotal = a.montant_total;
            o.dataset.montantLignes = a.montant_lignes;
            sel.appendChild(o);
        });
    }

    async function loadRubrique() {
        const id = document.getElementById('rubrique_id').value;
        const box = document.getElementById('chemin-nomenclature');
        if (!id) { box.innerHTML = ''; return; }
        const r = await fetch(URL_RUB + id, { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const d = await r.json();
        box.innerHTML = (d.chemin || []).map(c => `<div>${c}</div>`).join('')
            + (d.statut_ok ? '' : '<div class="text-warning mt-1">Rubrique non validée.</div>');
    }

    function buildRecap() {
        const st = document.getElementById('structure_id');
        const act = document.getElementById('activite_id');
        const rub = document.getElementById('rubrique_id');
        const src = document.getElementById('source_financement_id');
        const exOpt = selExercice.selectedOptions[0];
        document.getElementById('recap').innerHTML = `
            <dt class="col-4">Exercice</dt><dd class="col-8">${exOpt?.textContent || '—'}</dd>
            <dt class="col-4">Structure</dt><dd class="col-8">${st.selectedOptions[0]?.textContent || '—'}</dd>
            <dt class="col-4">Activité</dt><dd class="col-8">${act.value ? act.selectedOptions[0]?.textContent : 'Hors PAP'}</dd>
            <dt class="col-4">Rubrique</dt><dd class="col-8">${rub.selectedOptions[0]?.textContent || '—'}</dd>
            <dt class="col-4">Source</dt><dd class="col-8">${src.value ? src.selectedOptions[0]?.textContent : '—'}</dd>
            <dt class="col-4">Code / Montant</dt><dd class="col-8 sb-code">${document.getElementById('code').value} · ${fmt(document.getElementById('montant_ouvert').value)} XAF</dd>`;
        buildSynthese(act);
    }

    /** Synthèse budgétaire de l'activité PAP : enveloppe, lignes existantes, cette ligne, reste (Phase 7/8). */
    function buildSynthese(act) {
        const box = document.getElementById('synthese-pap');
        const o = act.selectedOptions[0];
        if (!act.value || !o) { box.classList.add('d-none'); return; }
        const pap = Number(o.dataset.montantTotal || 0);
        const lignes = Number(o.dataset.montantLignes || 0);
        const ce = Number(document.getElementById('montant_ouvert').value || 0);
        const total = lignes + ce;
        const reste = pap - total;
        const depasse = pap > 0 && reste < -0.01;
        document.getElementById('synthese-pap-body').innerHTML = `
            <table class="table table-sm mb-0 small">
                <tr><td>Enveloppe PAP de l'activité</td><td class="text-end sb-code">${fmt(pap)}</td></tr>
                <tr><td>Lignes déjà affectées</td><td class="text-end">${fmt(lignes)}</td></tr>
                <tr><td>Cette ligne</td><td class="text-end text-primary">${fmt(ce)}</td></tr>
                <tr class="border-top"><td class="fw-semibold">Total après création</td><td class="text-end fw-semibold">${fmt(total)}</td></tr>
                <tr><td class="fw-semibold">Reste disponible</td><td class="text-end fw-semibold ${depasse ? 'text-danger' : 'text-success'}">${fmt(reste)}</td></tr>
            </table>
            ${depasse ? '<div class="small text-danger mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Dépassement de l\'enveloppe PAP.</div>' : ''}`;
        box.classList.remove('d-none');
    }

    async function runVerifier() {
        const body = new URLSearchParams({
            exercice_id: hidExercice.value,
            structure_id: document.getElementById('structure_id').value,
            activite_id: document.getElementById('activite_id').value,
            rubrique_id: document.getElementById('rubrique_id').value,
            code: document.getElementById('code').value,
            montant_ouvert: document.getElementById('montant_ouvert').value,
        });
        const r = await fetch(`${URL_VER}?${body}`, { headers: { Accept: 'application/json' } });
        const box = document.getElementById('alertes-box');
        box.innerHTML = '';
        if (!r.ok) return;
        const d = await r.json();
        (d.erreurs || []).forEach(e => { box.innerHTML += `<div class="alert alert-danger mb-1">${e}</div>`; });
        (d.alertes || []).forEach(a => { box.innerHTML += `<div class="alert alert-warning mb-1">${a}</div>`; });
        if (d.ok && !(d.alertes || []).length) box.innerHTML = '<div class="alert alert-success mb-0">Contrôles OK — prêt à créer.</div>';
        btnSubmit.disabled = !d.ok || !exerciceModifiable;
    }

    function showStepError(message) {
        const box = document.getElementById('wizard-step-error');
        if (!box) return;
        if (!message) { box.classList.add('d-none'); box.textContent = ''; return; }
        box.textContent = message;
        box.classList.remove('d-none');
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function validateCurrentStep() {
        showStepError('');
        if (step === 0 && !exerciceModifiable) { showStepError('Cet exercice est verrouillé.'); return false; }
        if (step === 1 && !document.getElementById('structure_id').value) { showStepError('Sélectionnez une structure.'); return false; }
        if (step === 3 && !document.getElementById('rubrique_id').value) { showStepError('Sélectionnez une rubrique.'); return false; }
        return true;
    }

    btnNext.addEventListener('click', () => { if (validateCurrentStep() && step < maxStep) { showStepError(''); showStep(step + 1); } });
    btnPrev.addEventListener('click', () => { showStepError(''); if (step > 0) showStep(step - 1); });

    selExercice.addEventListener('change', () => { loadContexte(); loadActivites(); });
    document.getElementById('structure_id').addEventListener('change', () => { loadContexte(); loadActivites(); });
    document.getElementById('activite_id').addEventListener('change', function () {
        const o = this.selectedOptions[0];
        document.getElementById('activite-detail').textContent = o?.dataset.pilier
            ? `Pilier : ${o.dataset.pilier} · Budget PAP : ${Number(o.dataset.montantTotal).toLocaleString('fr-FR')} · Lignes : ${Number(o.dataset.montantLignes).toLocaleString('fr-FR')}`
            : '';
    });
    document.getElementById('rubrique_id').addEventListener('change', loadRubrique);
    document.getElementById('filtre_partenaire').addEventListener('change', function () {
        const pid = this.value;
        document.querySelectorAll('#source_financement_id option').forEach(o => {
            if (!o.value) return;
            o.hidden = pid && o.dataset.partenaire !== pid;
        });
    });
    ['code', 'montant_ouvert'].forEach(id => document.getElementById(id).addEventListener('input', (e) => {
        if (e.target.id === 'code') codeEdite = true;
        if (step === maxStep) { buildRecap(); runVerifier(); }
    }));

    document.getElementById('wizard-form').addEventListener('submit', e => {
        if (!exerciceModifiable) { e.preventDefault(); showStepError('Exercice verrouillé.'); showStep(0); }
    });

    loadContexte();
    if (document.getElementById('structure_id').value) loadActivites();
})();
</script>@endpush
