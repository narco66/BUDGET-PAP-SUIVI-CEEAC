@php $pap = $pap ?? null; @endphp
<div class="row g-3">
    @if(! $pap)
        <x-form.select name="exercice_id" label="Exercice" col="4" :value="old('exercice_id')" required placeholder="— Choisir —">
            @foreach($exercices as $ex)
                <option value="{{ $ex->id }}" @selected(old('exercice_id') == $ex->id)>{{ $ex->annee }} — {{ $ex->libelle }}</option>
            @endforeach
        </x-form.select>
        <x-form.input name="reference" label="Référence" col="4" :value="old('reference', $referenceSuggestion ?? '')" placeholder="PAP-2027" />
    @else
        <div class="col-md-4">
            <label class="form-label">Référence</label>
            <input type="text" class="form-control" value="{{ $pap->reference }}" disabled>
        </div>
        <div class="col-md-4">
            <label class="form-label">Exercice</label>
            <input type="text" class="form-control" value="{{ $pap->exercice?->annee }}" disabled>
        </div>
    @endif
    <x-form.input name="libelle" label="Intitulé" col="4" :value="$pap?->libelle" required />
    <x-form.select name="structure_id" label="Structure porteuse (département / direction)" col="6" :value="$pap?->structure_id" placeholder="— Non rattaché —">
        @foreach($structures as $s)
            <option value="{{ $s->id }}" @selected(old('structure_id', $pap?->structure_id) == $s->id)>
                [{{ $s->type }}] {{ $s->code }} — {{ Str::limit($s->libelle, 40) }}
            </option>
        @endforeach
    </x-form.select>
    <x-form.select name="responsable_id" label="Responsable métier" col="6" :value="$pap?->responsable_id" placeholder="— Non désigné —">
        @foreach($responsables as $u)
            <option value="{{ $u->id }}" @selected(old('responsable_id', $pap?->responsable_id) == $u->id)>{{ $u->name }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="date_elaboration" label="Date d'élaboration" type="date" col="4"
        :value="old('date_elaboration', $pap?->date_elaboration?->format('Y-m-d') ?? now()->format('Y-m-d'))" />
    <x-form.textarea name="observations" label="Observations" col="12" :value="$pap?->observations" :rows="3" />
</div>
