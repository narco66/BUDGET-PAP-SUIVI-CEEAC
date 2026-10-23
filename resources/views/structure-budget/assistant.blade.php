@extends('layouts.app')
@section('titre', 'Assistant — Structure du budget')

@section('contenu')
<a href="{{ route('structure-budget.arbre', ['exercice' => $exercice?->id]) }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Structure du budget</a>
<h4 class="fw-bold mb-1">Assistant de saisie d'une ligne budgétaire</h4>
<p class="text-muted small">Rattachez une ligne à une structure de l'organigramme. Le système propose le code, les référentiels et contrôle la cohérence.</p>

<form method="POST" action="{{ route('structure-budget.ligne.store') }}" id="sb-form">
    @csrf
    <input type="hidden" name="exercice_id" value="{{ $exercice?->id }}">
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Exercice</label>
                <input class="form-control" value="{{ $exercice?->libelle ?? $exercice?->annee }}" disabled>
            </div>
            <x-form.select name="structure_id" label="Structure de rattachement" col="6" id="structure_id" :value="request('structure')" required>
                @foreach ($structures as $s)
                    <option value="{{ $s->id }}" @selected((int) request('structure') === $s->id)>{{ $s->code }} · {{ $s->sigle ?: $s->libelle }} ({{ str_replace('_', ' ', $s->type) }})</option>
                @endforeach
            </x-form.select>
            <x-form.select name="activite_id" label="Activité (PAP) rattachée" col="6" id="activite_id" :value="old('activite_id')" placeholder="— Aucune / sélectionner une structure —"
                help="Filtrée par la structure choisie (cohérence PAP)." />
            <x-form.select name="rubrique_id" label="Rubrique (nomenclature)" col="6" :value="old('rubrique_id')" required>
                @foreach ($rubriques as $r)
                    <option value="{{ $r->id }}" @selected(old('rubrique_id') == $r->id)>{{ $r->code }} · {{ \Illuminate\Support\Str::limit($r->intitule, 40) }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="code" label="Code" col="4" :value="old('code', $codeSuggere)" required input-class="font-monospace"
                help="Code suggéré, modifiable." />
            <x-form.select name="source_financement_id" label="Source de financement" col="4" :value="old('source_financement_id')" placeholder="— Aucune —">
                @foreach ($sources as $sf)
                    <option value="{{ $sf->id }}" @selected(old('source_financement_id') == $sf->id)>{{ $sf->code }} · {{ $sf->libelle }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="montant_ouvert" label="Montant ouvert (XAF)" type="number" col="4" min="0" step="0.01"
                :value="old('montant_ouvert', 0)" required />
            <x-form.input name="intitule" label="Intitulé" col="12" :value="old('intitule')" maxlength="255" />
        </div>
        <x-form.footer :cancel-url="route('structure-budget.arbre', ['exercice' => $exercice?->id])" submit-label="Créer la ligne" submit-icon="bi-check-lg" class="border-0" />
    </div>
</form>
@endsection

@push('scripts')<script>
(function () {
    const URL_ACT = "{{ route('structure-budget.assistant.activites') }}";
    const structureSel = document.getElementById('structure_id');
    const activiteSel = document.getElementById('activite_id');

    async function chargerActivites() {
        const id = structureSel.value;
        activiteSel.innerHTML = '<option value="">— Aucune / sélectionner une structure —</option>';
        if (!id) return;
        const r = await fetch(`${URL_ACT}?structure_id=${id}&exercice_id={{ $exercice?->id }}`, { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const d = await r.json();
        (d.activites || []).forEach(a => {
            const o = document.createElement('option');
            o.value = a.id;
            o.textContent = `${a.code} · ${a.intitule}`;
            activiteSel.appendChild(o);
        });
    }
    structureSel?.addEventListener('change', chargerActivites);
    if (structureSel?.value) chargerActivites();
})();
</script>@endpush
