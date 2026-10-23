@extends('layouts.app')
@section('titre', 'Modifier '.$budget->reference)

@section('contenu')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Modifier {{ $budget->reference }}</h5>
                <form method="POST" action="{{ route('budget.enveloppe.update', $budget) }}">@csrf @method('PUT')
                    <x-form.input name="libelle" label="Libellé" :value="$budget->libelle" required />
                    <div class="row g-3">
                        <x-form.select name="structure_id" label="Structure" col="6" :value="$budget->structure_id" placeholder="—">
                            @foreach($structures as $s)
                                <option value="{{ $s->id }}" @selected(old('structure_id', $budget->structure_id)==$s->id)>{{ $s->libelle }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.select name="responsable_id" label="Responsable" col="6" :value="$budget->responsable_id" placeholder="—">
                            @foreach($responsables as $u)
                                <option value="{{ $u->id }}" @selected(old('responsable_id', $budget->responsable_id)==$u->id)>{{ $u->name }}</option>
                            @endforeach
                        </x-form.select>
                    </div>
                    <x-form.input name="date_elaboration" label="Date d'élaboration" type="date"
                        :value="old('date_elaboration', $budget->date_elaboration?->format('Y-m-d'))" />
                    <x-form.textarea name="observations" label="Observations" :value="$budget->observations" :rows="2" />
                    <x-form.actions :cancel-url="route('budget.enveloppe.show', $budget)" />
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
