@csrf
@php $l = $ligne ?? null; @endphp
<input type="hidden" name="exercice_id" value="{{ old('exercice_id', $l->exercice_id ?? $exercice?->id) }}">
<div class="row g-3">
    <x-form.input name="code" label="Code ligne" col="4" :value="$l->code ?? ''" required />
    <x-form.input name="intitule" label="Intitulé" col="8" :value="$l->intitule ?? ''" />
    <x-form.select name="rubrique_id" label="Rubrique (nature)" col="6" :value="$l->rubrique_id ?? ''" required>
        @foreach ($rubriques as $r)
            <option value="{{ $r->id }}" @selected(old('rubrique_id', $l->rubrique_id ?? '')==$r->id)>{{ $r->code }} · {{ $r->intitule }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="structure_id" label="Structure" col="6" :value="$l->structure_id ?? ''" placeholder="— Non rattachée —">
        @foreach ($structures as $s)
            <option value="{{ $s->id }}" @selected(old('structure_id', $l->structure_id ?? '')==$s->id)>{{ $s->code }} · {{ $s->libelle }}</option>
        @endforeach
    </x-form.select>
    <x-form.select name="source_financement_id" label="Source principale (compatibilité)" col="6" :value="$l->source_financement_id ?? ''" placeholder="— Non défini —">
        @foreach ($sources as $src)
            <option value="{{ $src->id }}" @selected(old('source_financement_id', $l->source_financement_id ?? '')==$src->id)>{{ $src->code }} · {{ $src->libelle }}</option>
        @endforeach
    </x-form.select>
    <div class="col-12">
        <label class="form-label">Ventilation multi-sources <span class="text-muted small">(somme = crédit alloué)</span></label>
        @php
            $rows = old('sources', $financementSources ?? []);
            if ($rows === []) { $rows = [['source_financement_id' => '', 'montant' => '', 'est_principale' => true]]; }
        @endphp
        <div class="table-responsive">
            <table class="table table-sm align-middle" id="sources-table">
                <thead class="table-light"><tr><th>Source</th><th style="width:180px">Montant (XAF)</th><th style="width:100px">Principale</th></tr></thead>
                <tbody>
                @foreach ($rows as $i => $row)
                    <tr>
                        <td>
                            <select name="sources[{{ $i }}][source_financement_id]" class="form-select form-select-sm @error('sources.'.$i.'.source_financement_id') is-invalid @enderror">
                                <option value="">— Choisir —</option>
                                @foreach ($sources as $src)
                                    <option value="{{ $src->id }}" @selected(($row['source_financement_id'] ?? '')==$src->id)>{{ $src->code }} · {{ $src->libelle }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input name="sources[{{ $i }}][montant]" value="{{ $row['montant'] ?? '' }}" class="form-control form-control-sm @error('sources.'.$i.'.montant') is-invalid @enderror" placeholder="0"></td>
                        <td class="text-center"><input type="radio" name="source_principale_idx" value="{{ $i }}" @checked($row['est_principale'] ?? ($i===0)) onclick="document.querySelector('[name=\'sources[{{ $i }}][est_principale]\']').value=1"></td>
                        <input type="hidden" name="sources[{{ $i }}][est_principale]" value="{{ ($row['est_principale'] ?? ($i===0)) ? 1 : 0 }}">
                    </tr>
                @endforeach
                @for ($j = count($rows); $j < min(3, count($rows) + 2); $j++)
                    <tr>
                        <td>
                            <select name="sources[{{ $j }}][source_financement_id]" class="form-select form-select-sm">
                                <option value="">— Choisir —</option>
                                @foreach ($sources as $src)<option value="{{ $src->id }}">{{ $src->code }} · {{ $src->libelle }}</option>@endforeach
                            </select>
                        </td>
                        <td><input name="sources[{{ $j }}][montant]" class="form-control form-control-sm" placeholder="0"></td>
                        <td class="text-center"><input type="radio" name="source_principale_idx" value="{{ $j }}"></td>
                        <input type="hidden" name="sources[{{ $j }}][est_principale]" value="0">
                    </tr>
                @endfor
                </tbody>
            </table>
        </div>
        @error('sources')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <x-form.input name="montant_ouvert" label="Crédit ouvert (XAF)" col="6" :value="$l->montant_ouvert ?? 0" required />
    <x-form.input name="montant_revise" label="Crédit révisé (XAF)" col="6" :value="$l->montant_revise ?? ''" placeholder="Identique à l'ouvert si vide"
        :help="$l?->montant_engage > 0 ? 'Engagé : '.number_format($l->montant_engage, 0, ',', ' ').' XAF' : null" />
    <x-form.select name="activite_id" label="Activité RBM (optionnel)" col="6" :value="$l->activite_id ?? ''" placeholder="— Non rattachée —">
        @foreach ($activites ?? [] as $a)
            <option value="{{ $a->id }}" @selected(old('activite_id', $l->activite_id ?? '')==$a->id)>{{ $a->code }} · {{ \Illuminate\Support\Str::limit($a->intitule, 40) }}</option>
        @endforeach
    </x-form.select>
</div>
<x-form.actions :cancel-url="route('budget.index', ['exercice'=>$exercice?->id])" />
