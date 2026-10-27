@extends('layouts.app')
@section('titre', 'Créer une enveloppe budgétaire')

@section('contenu')
@php
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $selectedExerciceId = old('exercice_id', $exercices->first()?->id);
@endphp

<div class="row justify-content-center">
    <div class="col-xl-9 col-lg-10">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h4 class="fw-bold mb-1">Nouvelle enveloppe budgétaire</h4>
                <div class="text-muted small">Initialisation du budget institutionnel avant préparation, arbitrage et adoption.</div>
            </div>
            <a href="{{ route('budget.enveloppe.index') }}" class="btn btn-sm btn-light">
                <i class="bi bi-arrow-left me-1"></i>Retour
            </a>
        </div>

        @if($exercices->isEmpty())
            <div class="alert alert-info border-0 shadow-sm">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Aucun exercice disponible</div>
                Toutes les enveloppes budgétaires ont déjà été créées ou aucun exercice n'est ouvert dans le référentiel.
            </div>
        @else
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <form method="POST" action="{{ route('budget.enveloppe.store') }}" novalidate>
                                @csrf
                                <x-form.select name="exercice_id" label="Exercice" id="exercice_id" :value="$selectedExerciceId" required
                                    help="Une seule enveloppe institutionnelle est autorisée par exercice." :placeholder="false">
                                    @foreach($exercices as $ex)
                                        <option value="{{ $ex->id }}" data-reference="{{ $syntheses[$ex->id]['reference'] ?? '' }}" @selected((int) $selectedExerciceId === (int) $ex->id)>
                                            Exercice {{ $ex->annee }} · {{ ucfirst($ex->statut) }} · {{ $ex->devise ?: 'XAF' }}
                                        </option>
                                    @endforeach
                                </x-form.select>
                                <x-form.input name="reference" label="Référence" id="reference" :value="old('reference', $referenceSuggestion)"
                                    maxlength="40" placeholder="BUDGET-2026"
                                    help="Laissez vide pour générer automatiquement la référence standard." />
                                <x-form.input name="libelle" label="Libellé" id="libelle" :value="old('libelle')" required maxlength="255"
                                    placeholder="Budget institutionnel 2026" />
                                <div class="row g-3">
                                    <x-form.select name="structure_id" label="Structure pilote" id="structure_id" col="6" :value="old('structure_id')" placeholder="Non renseignée">
                                        @foreach($structures as $s)
                                            <option value="{{ $s->id }}" @selected((int) old('structure_id') === (int) $s->id)>
                                                {{ $s->code }} · {{ $s->libelle }}{{ $s->type ? ' · '.$s->type : '' }}
                                            </option>
                                        @endforeach
                                    </x-form.select>
                                    <x-form.select name="responsable_id" label="Responsable" id="responsable_id" col="6" :value="old('responsable_id')" placeholder="Non renseigné">
                                        @foreach($responsables as $u)
                                            <option value="{{ $u->id }}" @selected((int) old('responsable_id') === (int) $u->id)>{{ $u->name }}</option>
                                        @endforeach
                                    </x-form.select>
                                </div>
                                <x-form.input name="date_elaboration" label="Date d'élaboration" id="date_elaboration" type="date"
                                    :value="old('date_elaboration', now()->toDateString())" />
                                <x-form.textarea name="observations" label="Observations" id="observations" :value="old('observations')" :rows="3"
                                    maxlength="2000" placeholder="Hypothèses, base juridique, contraintes de cadrage ou points d'attention." />
                                <x-form.actions :cancel-url="route('budget.enveloppe.index')" submit-label="Créer l'enveloppe" submit-icon="bi-check2-circle" />
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Contrôles préalables</h6>
                            @foreach($syntheses as $id => $s)
                                <div class="js-synthese {{ (int) $selectedExerciceId === (int) $id ? '' : 'd-none' }}" data-exercice="{{ $id }}">
                                    <div class="small text-muted">Exercice</div>
                                    <div class="fw-semibold mb-3">{{ $s['annee'] }} · {{ ucfirst($s['statut']) }} · {{ $s['devise'] }}</div>
                                    <div class="d-flex justify-content-between py-2 border-top">
                                        <span class="text-muted">Lignes budgétaires</span>
                                        <strong>{{ $s['lignes'] }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-top">
                                        <span class="text-muted">Dépenses cadrées</span>
                                        <strong>{{ $fmt($s['depenses']) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-top">
                                        <span class="text-muted">Recettes validées</span>
                                        <strong>{{ $fmt($s['recettes']) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-top border-bottom">
                                        <span class="text-muted">Solde indicatif</span>
                                        <strong class="{{ $s['solde'] < 0 ? 'text-danger' : 'text-success' }}">{{ $fmt($s['solde']) }}</strong>
                                    </div>
                                    @if($s['lignes'] === 0)
                                        <div class="alert alert-warning small mt-3 mb-0">Aucune ligne budgétaire n'est encore rattachée à cet exercice.</div>
                                    @elseif($s['recettes'] <= 0)
                                        <div class="alert alert-warning small mt-3 mb-0">Aucune prévision de recette validée n'est disponible pour rapprocher l'enveloppe.</div>
                                    @else
                                        <div class="alert alert-success small mt-3 mb-0">Les données de cadrage existent pour préparer l'enveloppe.</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script @if(!empty($cspNonce)) nonce="{{ $cspNonce }}" @endif>
document.getElementById('exercice_id')?.addEventListener('change', (event) => {
    document.querySelectorAll('.js-synthese').forEach((item) => {
        item.classList.toggle('d-none', item.dataset.exercice !== event.target.value);
    });
    const reference = document.getElementById('reference');
    const selected = event.target.selectedOptions[0];
    if (reference && selected && (reference.value === '' || /^BUDGET-\d{4}$/.test(reference.value))) {
        reference.value = selected.dataset.reference || '';
    }
});
</script>
@endpush
