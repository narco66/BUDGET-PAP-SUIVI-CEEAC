@php
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $couleurs = ['brouillon'=>'secondary','en_validation'=>'warning','valide'=>'info','publie'=>'success','rejete'=>'danger','clos'=>'dark','en_revision'=>'primary'];
    $barClass = fn ($t) => $t >= 75 ? 'bg-success' : ($t >= 50 ? 'bg-warning' : 'bg-danger');
@endphp

<div class="table-responsive">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>Code</th>
                <th>Produit</th>
                <th class="text-center">Act.</th>
                <th class="text-end">Budget</th>
                <th class="text-end">Payé</th>
                <th style="min-width:120px">Physique</th>
                <th style="min-width:120px">Financier</th>
                <th class="text-center">Indice</th>
            </tr>
        </thead>
        <tbody>
            @forelse($produits as $p)
            <tr>
                <td><code>{{ $p['code'] }}</code></td>
                <td>{{ Str::limit($p['intitule'], 45) }}</td>
                <td class="text-center">{{ $p['activites'] }}</td>
                <td class="text-end small">{{ $fmt($p['budget_prevu']) }}</td>
                <td class="text-end small">{{ $fmt($p['budget_paye']) }}</td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar {{ $barClass($p['taux_physique']) }}" style="width:{{ min($p['taux_physique'], 100) }}%"></div></div>
                        <span class="small text-muted">{{ $p['taux_physique'] }}%</span>
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar {{ $barClass($p['taux_financier']) }}" style="width:{{ min($p['taux_financier'], 100) }}%"></div></div>
                        <span class="small text-muted">{{ $p['taux_financier'] }}%</span>
                    </div>
                </td>
                <td class="text-center fw-semibold">{{ $p['taux_physique'] > 0 || $p['taux_financier'] > 0 ? round(($p['taux_physique'] + $p['taux_financier']) / 2, 1) : 0 }}%</td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-muted py-3">Aucun produit.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
