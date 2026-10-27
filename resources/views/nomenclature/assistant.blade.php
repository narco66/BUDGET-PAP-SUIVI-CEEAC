@extends('layouts.app')
@section('titre', 'Saisie assistée de la nomenclature')

@push('styles')<style>
    .nom-code { font-family:monospace; color:#0a4a8f; }
    #freres { max-height:230px; overflow:auto; }
    .badge-dispo-ok { background:#d1e7dd; color:#0f5132; }
    .badge-dispo-ko { background:#f8d7da; color:#842029; }
</style>@endpush

@section('contenu')
<a href="{{ route('nomenclature.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Nomenclature</a>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Saisie assistée ligne par ligne</h4>
        <span class="text-muted small">Le système propose le parent, le niveau et le prochain code disponible. Contrôle d'unicité en temps réel.</span>
    </div>
</div>

<div class="row g-3" id="assistant">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Niveau *</label>
                    <select id="niveau" class="form-select">
                        @foreach ($niveaux as $cle => $n)
                            <option value="{{ $cle }}">{{ $n['cdc'] }} ({{ $n['label'] }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-7" id="bloc-sens" style="display:none">
                    <label class="form-label">Sens budgétaire *</label>
                    <select id="sens" class="form-select">
                        <option value="">— Sélectionner —</option>
                        @foreach ($sensListe as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>

                <div class="col-md-7" id="bloc-parent">
                    <label class="form-label" id="parent-label">Parent de rattachement *</label>
                    <select id="parent" class="form-select"><option value="">— Sélectionner —</option></select>
                </div>

                <div class="col-12"><div id="chemin" class="small text-muted"></div></div>

                <div class="col-md-5">
                    <label class="form-label">Code *</label>
                    <input id="code" class="form-control nom-code" autocomplete="off" placeholder="Code">
                    <div id="dispo" class="form-text"></div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ordre</label>
                    <input id="ordre" type="number" min="0" value="0" class="form-control">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Intitulé *</label>
                    <input id="intitule" class="form-control" autocomplete="off" placeholder="Intitulé">
                </div>

                <div class="col-12 text-end">
                    <button id="btn-creer" class="btn btn-primary" disabled><i class="bi bi-plus-lg me-1"></i>Créer et continuer</button>
                </div>
                <div class="col-12">
                    <div id="assistant-feedback" class="alert alert-danger d-none mb-0" role="alert"></div>
                </div>
            </div>
        </div></div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Éléments existants dans ce périmètre</div>
            <div class="card-body p-0"><div id="freres" class="list-group list-group-flush small">
                <div class="list-group-item text-muted">Sélectionnez un niveau et un parent.</div>
            </div></div>
        </div>
        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold d-flex justify-content-between">
            <span>Créés dans cette session</span><span id="compteur" class="badge bg-success">0</span></div>
            <div class="card-body p-0"><div id="session" class="list-group list-group-flush small">
                <div class="list-group-item text-muted">Aucun élément créé pour l'instant.</div>
            </div></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
(function () {
    const NIVEAUX = @json($niveaux);
    const CHAPITRES = @json($chapitres);
    const ARTICLES = @json($articles);
    const PARAGRAPHES = @json($paragraphes);
    const SENS = @json($sensListe);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const URL_CONTEXTE = "{{ route('nomenclature.assistant.contexte') }}";
    const URL_VERIFIER = "{{ route('nomenclature.assistant.verifier') }}";
    const URL_BASE = "{{ url('nomenclature') }}";

    const codeTitre = id => (CHAPITRES.find(c => c.id === id) || {}).code || '';
    const codeArticle = id => (ARTICLES.find(a => a.id === id) || {}).code || '';

    const el = id => document.getElementById(id);
    const niveauSel = el('niveau'), sensSel = el('sens'), parentSel = el('parent'),
          codeIn = el('code'), intituleIn = el('intitule'), ordreIn = el('ordre'),
          btn = el('btn-creer'), dispoBox = el('dispo'), cheminBox = el('chemin'),
          feedbackBox = el('assistant-feedback');

    const champsAssistant = [sensSel, parentSel, codeIn, intituleIn, ordreIn];

    function clearAssistantFeedback() {
        if (!feedbackBox) return;
        feedbackBox.classList.add('d-none');
        feedbackBox.textContent = '';
        champsAssistant.forEach(c => c?.classList.remove('is-invalid'));
    }

    function showAssistantFeedback(message, fields = []) {
        if (!feedbackBox) return;
        if (!message) { clearAssistantFeedback(); return; }
        feedbackBox.textContent = message;
        feedbackBox.classList.remove('d-none');
        champsAssistant.forEach(c => c?.classList.remove('is-invalid'));
        fields.forEach(f => f?.classList.add('is-invalid'));
        feedbackBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function champsDepuisErreurs(errors) {
        const fields = [];
        const keys = Object.keys(errors || {});
        if (keys.some(k => k === 'code')) fields.push(codeIn);
        if (keys.some(k => k === 'intitule')) fields.push(intituleIn);
        if (keys.some(k => k === 'sens')) fields.push(sensSel);
        if (keys.some(k => k.includes('titre_id') || k.includes('article_id') || k.includes('paragraphe_id'))) fields.push(parentSel);
        return fields;
    }

    let dispoOk = false, debounce = null;

    function niveauActuel() { return niveauSel.value; }
    function estTitre() { return niveauActuel() === 'titre'; }

    function remplirParents() {
        const niv = niveauActuel();
        let opts = '<option value="">— Sélectionner —</option>';
        if (niv === 'article') {
            CHAPITRES.forEach(c => { opts += `<option value="${c.id}">[${SENS[c.sens] || c.sens}] ${c.code} · ${c.intitule}</option>`; });
            el('parent-label').textContent = 'Chapitre de rattachement *';
        } else if (niv === 'paragraphe') {
            ARTICLES.forEach(a => { opts += `<option value="${a.id}">${codeTitre(a.titre_id)}/${a.code} · ${a.intitule}</option>`; });
            el('parent-label').textContent = 'Article de rattachement *';
        } else if (niv === 'rubrique') {
            PARAGRAPHES.forEach(p => { opts += `<option value="${p.id}">${codeArticle(p.article_id)}/${p.code} · ${p.intitule}</option>`; });
            el('parent-label').textContent = 'Paragraphe de rattachement *';
        }
        parentSel.innerHTML = opts;
    }

    function basculerEntrees() {
        el('bloc-sens').style.display = estTitre() ? '' : 'none';
        el('bloc-parent').style.display = estTitre() ? 'none' : '';
    }

    function parametresPerimetre() {
        const p = { niveau: niveauActuel() };
        if (estTitre()) { p.sens = sensSel.value; } else { p.parent_id = parentSel.value; }
        return p;
    }

    function perimetreDefini() {
        return estTitre() ? !!sensSel.value : !!parentSel.value;
    }

    async function chargerContexte() {
        cheminBox.textContent = ''; codeIn.value = ''; majDispo(null);
        rendreFreres([]);
        if (!perimetreDefini()) return;
        const q = new URLSearchParams(parametresPerimetre());
        const r = await fetch(`${URL_CONTEXTE}?${q}`, { headers: { 'Accept': 'application/json' } });
        if (!r.ok) return;
        const data = await r.json();
        if (data.chemin) cheminBox.innerHTML = `<i class="bi bi-diagram-3 me-1"></i>Parent : <span class="nom-code">${data.chemin}</span>`;
        rendreFreres(data.freres || []);
        if (data.prochain_code) { codeIn.value = data.prochain_code; verifierCode(); }
        else { codeIn.focus(); }
    }

    function rendreFreres(freres) {
        const box = el('freres');
        if (!freres.length) { box.innerHTML = '<div class="list-group-item text-muted">Aucun élément à ce niveau.</div>'; return; }
        box.innerHTML = freres.map(f => `<div class="list-group-item d-flex gap-2"><span class="nom-code">${f.code}</span><span class="text-truncate">${f.intitule}</span></div>`).join('');
    }

    function majDispo(ok) {
        dispoOk = ok === true;
        if (ok === null) { dispoBox.innerHTML = ''; }
        else if (ok) { dispoBox.innerHTML = '<span class="badge badge-dispo-ok">Code disponible</span>'; }
        else { dispoBox.innerHTML = '<span class="badge badge-dispo-ko">Code déjà utilisé</span>'; }
        majBouton();
    }

    function majBouton() {
        btn.disabled = !(dispoOk && codeIn.value.trim() && intituleIn.value.trim() && perimetreDefini());
    }

    async function verifierCode() {
        const code = codeIn.value.trim();
        if (!code || !perimetreDefini()) { majDispo(null); return; }
        const q = new URLSearchParams({ ...parametresPerimetre(), code });
        const r = await fetch(`${URL_VERIFIER}?${q}`, { headers: { 'Accept': 'application/json' } });
        if (!r.ok) { majDispo(null); return; }
        const data = await r.json();
        majDispo(data.disponible === true);
    }

    async function creer() {
        clearAssistantFeedback();
        if (!perimetreDefini()) {
            showAssistantFeedback('Complétez le périmètre (sens ou parent).', estTitre() ? [sensSel] : [parentSel]);
            return;
        }
        if (!codeIn.value.trim() || !intituleIn.value.trim()) {
            const fields = [];
            if (!codeIn.value.trim()) fields.push(codeIn);
            if (!intituleIn.value.trim()) fields.push(intituleIn);
            showAssistantFeedback('Code et intitulé sont obligatoires.', fields);
            return;
        }
        if (!dispoOk) {
            showAssistantFeedback('Le code n\'est pas disponible.', [codeIn]);
            return;
        }
        const niv = niveauActuel();
        const body = new URLSearchParams({
            code: codeIn.value.trim(),
            intitule: intituleIn.value.trim(),
            ordre: ordreIn.value || 0,
        });
        if (estTitre()) { body.append('sens', sensSel.value); }
        else { body.append(NIVEAUX[niv].fk, parentSel.value); }

        btn.disabled = true;
        const r = await fetch(`${URL_BASE}/${niv}`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': CSRF },
            body,
        });
        if (r.status === 422) {
            const data = await r.json();
            const messages = Object.values(data.errors || { e: ['Erreur de validation.'] }).flat();
            showAssistantFeedback(messages.join(' '), champsDepuisErreurs(data.errors));
            majBouton();
            return;
        }
        if (!r.ok) { showAssistantFeedback('Erreur lors de l\'enregistrement.'); majBouton(); return; }
        const data = await r.json();
        ajouterSession(NIVEAUX[niv].cdc, data.element);
        // Enchaîner : code suivant suggéré, intitulé vidé, focus.
        intituleIn.value = '';
        codeIn.value = data.prochain_code || '';
        rafraichirReferentiel(niv, data.element);
        await chargerContexte();
        intituleIn.focus();
    }

    // Ajoute l'élément fraîchement créé aux jeux de données en mémoire pour qu'il devienne
    // immédiatement sélectionnable comme parent sans recharger la page.
    function rafraichirReferentiel(niv, element) {
        const item = { id: element.id, code: element.code, intitule: element.intitule };
        if (niv === 'titre') { item.sens = sensSel.value; CHAPITRES.push(item); }
        else if (niv === 'article') { item.titre_id = parseInt(parentSel.value); ARTICLES.push(item); }
        else if (niv === 'paragraphe') { item.article_id = parseInt(parentSel.value); PARAGRAPHES.push(item); }
    }

    let nbSession = 0;
    function ajouterSession(label, element) {
        nbSession++;
        el('compteur').textContent = nbSession;
        const box = el('session');
        if (nbSession === 1) box.innerHTML = '';
        const ligne = document.createElement('div');
        ligne.className = 'list-group-item d-flex gap-2';
        ligne.innerHTML = `<span class="badge bg-light text-dark">${label}</span><span class="nom-code">${element.code}</span><span class="text-truncate">${element.intitule}</span>`;
        box.prepend(ligne);
    }

    // --- Événements ---
    niveauSel.addEventListener('change', () => { clearAssistantFeedback(); basculerEntrees(); remplirParents(); chargerContexte(); });
    sensSel.addEventListener('change', () => { clearAssistantFeedback(); chargerContexte(); });
    parentSel.addEventListener('change', () => { clearAssistantFeedback(); chargerContexte(); });
    intituleIn.addEventListener('input', () => { clearAssistantFeedback(); majBouton(); });
    codeIn.addEventListener('input', () => { clearAssistantFeedback(); majDispo(null); clearTimeout(debounce); debounce = setTimeout(verifierCode, 300); });
    btn.addEventListener('click', creer);

    // Init
    basculerEntrees(); remplirParents();
})();
</script>@endpush
