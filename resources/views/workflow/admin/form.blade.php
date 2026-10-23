@extends('layouts.app')
@section('titre', $workflow->exists ? 'Modifier '.$workflow->code : 'Nouveau circuit')

@section('contenu')
<div class="mb-3">
    <a href="{{ route('admin.workflows.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>

@if (session('succes'))<div class="alert alert-success">{{ session('succes') }}</div>@endif

<form method="POST" action="{{ $workflow->exists ? route('admin.workflows.update', $workflow) : route('admin.workflows.store') }}">
    @csrf
    @if ($workflow->exists) @method('PUT') @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Identité du circuit</h5>
            <div class="row g-3">
                <x-form.input name="code" label="Code" col="3" :value="old('code', $workflow->code)" required maxlength="30" @readonly($workflow->exists) />
                <x-form.input name="libelle" label="Libellé" col="5" :value="old('libelle', $workflow->libelle)" required />
                <x-form.input name="type_objet" label="Type d'objet" col="4" :value="old('type_objet', $workflow->type_objet)" required placeholder="pap, budget, besoin…" />
                <x-form.textarea name="description" label="Description" col="12" :value="old('description', $workflow->description)" :rows="2" />
                <x-form.checkbox name="actif" label="Circuit actif (utilisable pour de nouvelles soumissions)" col="12" :checked="old('actif', $workflow->actif)" />
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Étapes</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-etape"><i class="bi bi-plus me-1"></i>Ajouter une étape</button>
        </div>
        <div id="etapes-container">
            @php $etapes = old('etapes', $workflow->etapes->map(fn ($e) => $e->toArray())->all() ?: [['ordre'=>1,'libelle'=>'','role'=>'','delai_jours'=>5]]); @endphp
            @foreach ($etapes as $i => $etape)
            <div class="row g-2 mb-2 etape-row align-items-end">
                @if (!empty($etape['id']))<input type="hidden" name="etapes[{{ $i }}][id]" value="{{ $etape['id'] }}">@endif
                <div class="col-md-1"><label class="form-label small">Ordre</label><input type="number" name="etapes[{{ $i }}][ordre]" class="form-control form-control-sm" value="{{ $etape['ordre'] ?? ($i+1) }}" min="1"></div>
                <div class="col-md-4"><label class="form-label small">Libellé</label><input type="text" name="etapes[{{ $i }}][libelle]" class="form-control form-control-sm" value="{{ $etape['libelle'] ?? '' }}" required></div>
                <div class="col-md-3"><label class="form-label small">Rôle validateur</label>
                    <select name="etapes[{{ $i }}][role]" class="form-select form-select-sm" required>
                        <option value="">—</option>
                        @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(($etape['role'] ?? '') === $role)>{{ str_replace('_',' ',$role) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label small">Délai (jours)</label><input type="number" name="etapes[{{ $i }}][delai_jours]" class="form-control form-control-sm" value="{{ $etape['delai_jours'] ?? 5 }}" min="1"></div>
                <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger w-100 remove-etape"><i class="bi bi-trash"></i></button></div>
            </div>
            @endforeach
        </div>
    </div></div>

    <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
</form>

@push('scripts')
@php
    $rolesOptions = $roles->map(fn ($role) => [
        'v' => $role,
        'l' => str_replace('_', ' ', $role),
    ])->values();
@endphp
<script>
    let etapeIdx = {{ count($etapes) }};
    const rolesOptions = @json($rolesOptions);
    document.getElementById('add-etape')?.addEventListener('click', () => {
        const c = document.getElementById('etapes-container');
        const opts = rolesOptions.map(r => `<option value="${r.v}">${r.l}</option>`).join('');
        c.insertAdjacentHTML('beforeend', `<div class="row g-2 mb-2 etape-row align-items-end">
            <div class="col-md-1"><label class="form-label small">Ordre</label><input type="number" name="etapes[${etapeIdx}][ordre]" class="form-control form-control-sm" value="${etapeIdx+1}" min="1"></div>
            <div class="col-md-4"><label class="form-label small">Libellé</label><input type="text" name="etapes[${etapeIdx}][libelle]" class="form-control form-control-sm" required></div>
            <div class="col-md-3"><label class="form-label small">Rôle</label><select name="etapes[${etapeIdx}][role]" class="form-select form-select-sm" required><option value="">—</option>${opts}</select></div>
            <div class="col-md-2"><label class="form-label small">Délai (j)</label><input type="number" name="etapes[${etapeIdx}][delai_jours]" class="form-control form-control-sm" value="5" min="1"></div>
            <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger w-100 remove-etape"><i class="bi bi-trash"></i></button></div>
        </div>`);
        etapeIdx++;
    });
    document.getElementById('etapes-container')?.addEventListener('click', e => {
        if (e.target.closest('.remove-etape')) e.target.closest('.etape-row')?.remove();
    });
</script>
@endpush
@endsection
