@php
    $avecEcart = $activitesEcarts->filter(fn ($a) => filled($a->cause_ecart) || filled($a->mesure_corrective));
@endphp

@if($avecEcart->isNotEmpty())
<p class="small text-muted mb-2">{{ $avecEcart->count() }} activité(s) avec analyse d'écarts renseignée.</p>
@endif

@can('gererGar', $pap)
<form action="{{ route('paps.ecarts.store', $pap) }}" method="POST">
    @csrf
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:90px">Code</th>
                    <th style="width:18%">Activité</th>
                    <th style="width:70px">Phys.</th>
                    <th style="width:70px">Fin.</th>
                    <th>Cause de l'écart</th>
                    <th>Mesure corrective</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activitesEcarts as $a)
                <tr>
                    <td><code>{{ $a->code }}</code></td>
                    <td class="small">{{ Str::limit($a->intitule, 40) }}</td>
                    <td class="small">{{ $a->taux_realisation_physique }}%</td>
                    <td class="small">{{ $a->taux_realisation_financier }}%</td>
                    <td>
                        <textarea name="ecarts[{{ $a->id }}][cause_ecart]" class="form-control form-control-sm" rows="2" placeholder="Cause de l'écart…">{{ old('ecarts.'.$a->id.'.cause_ecart', $a->cause_ecart) }}</textarea>
                    </td>
                    <td>
                        <textarea name="ecarts[{{ $a->id }}][mesure_corrective]" class="form-control form-control-sm" rows="2" placeholder="Mesure corrective…">{{ old('ecarts.'.$a->id.'.mesure_corrective', $a->mesure_corrective) }}</textarea>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-3">Aucune activité sur cet exercice.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($activitesEcarts->isNotEmpty())
    <div class="mt-3">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Enregistrer les écarts</button>
    </div>
    @endif
</form>
@else
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Code</th>
                <th>Activité</th>
                <th>Phys.</th>
                <th>Fin.</th>
                <th>Cause de l'écart</th>
                <th>Mesure corrective</th>
            </tr>
        </thead>
        <tbody>
            @forelse($activitesEcarts as $a)
            <tr>
                <td><code>{{ $a->code }}</code></td>
                <td class="small">{{ Str::limit($a->intitule, 40) }}</td>
                <td class="small">{{ $a->taux_realisation_physique }}%</td>
                <td class="small">{{ $a->taux_realisation_financier }}%</td>
                <td class="small">{{ $a->cause_ecart ?: '—' }}</td>
                <td class="small">{{ $a->mesure_corrective ?: '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-3">Aucune activité sur cet exercice.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endcan
