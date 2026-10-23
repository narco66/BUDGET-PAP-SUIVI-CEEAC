@extends('layouts.app')
@section('titre', 'Nouvel engagement')

@section('contenu')
<div class="card border-0 shadow-sm"><div class="card-body">
    <form action="{{ route('engagements.store') }}" method="POST" x-data="{ lignes: @js(old('imputations', $prefill ? [$prefill] : [['ligne_budgetaire_id'=>'','montant'=>'']])) }">
        @csrf
        <input type="hidden" name="exercice_id" value="{{ old('exercice_id', $exercice?->id) }}">
        @if($besoin)<input type="hidden" name="expression_besoin_id" value="{{ $besoin->id }}">
            <div class="alert alert-info small">
                <i class="bi bi-link-45deg me-1"></i>Issu du besoin <strong>{{ $besoin->numero }}</strong> — {{ $besoin->objet }}
                @if($besoin->ligneBudgetaire ?? $besoin->activite?->ligneBudgetaire)
                    <br>Imputation suggérée : <code>{{ ($besoin->ligneBudgetaire ?? $besoin->activite->ligneBudgetaire)->code }}</code>
                @endif
            </div>
        @endif

        <div class="row g-3">
            <x-form.select name="type" label="Type" col="3" :value="old('type', $besoin ? 'simple' : '')" required
                :options="['simple'=>'Simple','bon_commande'=>'Bon de commande','marche'=>'Marché','mission'=>'Mission','subvention'=>'Subvention','convention'=>'Convention','pluriannuel'=>'Pluriannuel','provisionnel'=>'Provisionnel']" />
            <x-form.input name="objet" label="Objet" col="6" :value="old('objet', $besoin?->objet)" required />
            <x-form.input name="date_engagement" label="Date" type="date" col="3" :value="old('date_engagement', date('Y-m-d'))" />
            <x-form.input name="beneficiaire" label="Bénéficiaire" col="6" :value="old('beneficiaire')" />
        </div>

        <hr>
        <h6 class="fw-bold"><i class="bi bi-diagram-2 me-1"></i>Imputations budgétaires (une ou plusieurs lignes)</h6>
        @error('imputations')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <table class="table table-sm align-middle">
            <thead><tr><th style="width:70%">Ligne budgétaire (code · disponible)</th><th>Montant (XAF)</th><th></th></tr></thead>
            <tbody>
                <template x-for="(l, i) in lignes" :key="i">
                    <tr>
                        <td>
                            <select :name="`imputations[${i}][ligne_budgetaire_id]`" x-model="l.ligne_budgetaire_id"
                                    class="form-select form-select-sm @error('imputations.*.ligne_budgetaire_id') is-invalid @enderror" required>
                                <option value="">— Choisir une ligne —</option>
                                @foreach ($lignes as $lb)
                                    <option value="{{ $lb->id }}">{{ $lb->code }} · {{ \Illuminate\Support\Str::limit($lb->intitule,30) }} · dispo {{ number_format($lb->disponible,0,',',' ') }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="number" step="0.01" :name="`imputations[${i}][montant]`" x-model="l.montant"
                                   class="form-control form-control-sm @error('imputations.*.montant') is-invalid @enderror" required>
                        </td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" @click="lignes.splice(i,1)" x-show="lignes.length>1"><i class="bi bi-x"></i></button></td>
                    </tr>
                </template>
            </tbody>
        </table>
        @error('imputations.*.ligne_budgetaire_id')<div class="text-danger small">{{ $message }}</div>@enderror
        @error('imputations.*.montant')<div class="text-danger small">{{ $message }}</div>@enderror
        <button type="button" class="btn btn-sm btn-outline-success" @click="lignes.push({ligne_budgetaire_id:'',montant:''})"><i class="bi bi-plus-lg me-1"></i>Ajouter une imputation</button>
        <div class="mt-2 fw-bold">Total : <span x-text="lignes.reduce((s,l)=>s+(parseFloat(l.montant)||0),0).toLocaleString('fr-FR')"></span> XAF</div>

        @if(!$lignes->count())<div class="alert alert-warning mt-2 small">Aucune ligne budgétaire pour cet exercice. Importez le budget d'abord.</div>@endif

        <x-form.actions :cancel-url="route('engagements.index')" submit-label="Créer l'engagement (brouillon)" class="mt-3" />
    </form>
</div></div>
@endsection
