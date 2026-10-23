@extends('layouts.app')
@section('titre', 'Nouvel encaissement')

@section('contenu')
<div class="mb-3"><a href="{{ route('encaissements.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a></div>

<form method="POST" action="{{ route('encaissements.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.select name="recette_id" label="Recette à encaisser" col="8" :value="old('recette_id', $recetteSelection)" required placeholder="—">
                @foreach ($recettes as $rec)
                    <option value="{{ $rec->id }}" @selected(old('recette_id', $recetteSelection)==$rec->id)>
                        {{ $rec->reference }} — {{ $rec->categorie?->libelle }} {{ $rec->etatMembre ? '('.$rec->etatMembre->pays.')' : '' }}
                        · prévu {{ number_format((float) $rec->montant_prevu, 0, ',', ' ') }} XAF
                    </option>
                @endforeach
            </x-form.select>
            <x-form.input name="reference" label="Référence" col="4" :value="old('reference')" maxlength="60" required />
            <x-form.input name="montant" label="Montant" type="number" col="3" step="0.01" min="0.01" :value="old('montant')" required />
            <x-form.input name="devise" label="Devise" col="2" :value="old('devise', 'XAF')" maxlength="3" required input-class="text-uppercase" />
            <x-form.input name="taux_change" label="Taux de change" type="number" col="3" step="0.000001" min="0.000001" :value="old('taux_change', 1)" required help="Contre-valeur = montant × taux." />
            <x-form.select name="mode_paiement" label="Mode de paiement" col="4" :value="old('mode_paiement', 'virement')" required :options="\App\Models\Encaissement::MODES_PAIEMENT" :placeholder="false" />
            <x-form.input name="date_encaissement" label="Date d'encaissement" type="date" col="4" :value="old('date_encaissement')" required />
            <x-form.input name="banque" label="Banque" col="4" :value="old('banque')" maxlength="120" />
            <x-form.input name="reference_bancaire" label="Référence bancaire" col="4" :value="old('reference_bancaire')" maxlength="100" />
            <x-form.textarea name="observations" label="Observations" col="12" :value="old('observations')" :rows="2" />
            <x-form.file name="justificatifs" label="Justificatifs (relevé bancaire, avis de crédit…)" col="12" multiple
                help="Vous pouvez joindre un ou plusieurs fichiers (PDF, image, Word, Excel — max 10 Mo chacun). D'autres pièces pourront être ajoutées ensuite depuis la fiche."
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" />
            <div class="col-12">
                <div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-1"></i>La validation (comptabilisation) reste impossible tant qu'aucun justificatif n'est rattaché.</div>
            </div>
        </div>
        <x-form.footer :cancel-url="route('encaissements.index')" />
    </div>
</form>
@endsection
