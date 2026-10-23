@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $barClass = fn ($t) => $t >= 75 ? 'bg-success' : ($t >= 50 ? 'bg-warning' : 'bg-danger');
@endphp

<div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>Code</th>
                <th>Activité</th>
                <th style="min-width:100px">Physique</th>
                <th style="min-width:100px">Financier</th>
                <th class="text-end">Payé</th>
                <th class="text-center">Indice</th>
                <th class="text-center">Alerte</th>
            </tr>
        </thead>
        <tbody>
            @forelse($activites as $a)
            <tr class="{{ ($a['en_retard'] || $a['surconsommation']) ? 'table-warning' : '' }}">
                <td><code>{{ $a['code'] }}</code></td>
                <td>{{ Str::limit($a['intitule'], 50) }}</td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar {{ $barClass($a['taux_physique']) }}" style="width:{{ min($a['taux_physique'], 100) }}%"></div></div>
                        <span class="small">{{ $a['taux_physique'] }}%</span>
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar {{ $a['surconsommation'] ? 'bg-danger' : $barClass($a['taux_financier']) }}" style="width:{{ min($a['taux_financier'], 100) }}%"></div></div>
                        <span class="small">{{ $a['taux_financier'] }}%</span>
                    </div>
                </td>
                <td class="text-end small">{{ $fmt($a['montant_paye']) }}</td>
                <td class="text-center fw-semibold">{{ $a['indice_performance'] }}%</td>
                <td class="text-center">
                    @if($a['surconsommation'])<span class="badge bg-danger" title="Surconsommation">$</span>@endif
                    @if($a['en_retard'])<span class="badge bg-warning text-dark" title="En retard">R</span>@endif
                    @if(!$a['en_retard'] && !$a['surconsommation'])<span class="text-muted">—</span>@endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-3">Aucune activité.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
