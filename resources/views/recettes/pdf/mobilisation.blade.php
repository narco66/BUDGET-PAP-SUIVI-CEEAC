<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Tableau de mobilisation des ressources</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
    h1 { font-size: 15px; color: #0a4a8f; margin-bottom: 2px; }
    .meta { color: #666; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #ddd; padding: 4px 5px; }
    th { background: #0a4a8f; color: #fff; }
    td.num { text-align: right; }
    tfoot td { font-weight: bold; background: #f0f4f8; }
    .retard { color: #b00020; font-weight: bold; }
</style></head>
<body>
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp
<h1>Tableau de mobilisation des contributions — CEEAC</h1>
<div class="meta">
    Exercice {{ $exercice?->annee ?? 'tous' }} · Généré le {{ now()->format('d/m/Y H:i') }}
</div>

<table>
    <thead>
        <tr><th>Code</th><th>État membre</th><th>Clé (%)</th><th>Appelé (XAF)</th><th>Recouvré (XAF)</th><th>Arriéré (XAF)</th><th>Taux</th></tr>
    </thead>
    <tbody>
    @foreach ($rows as $r)
        <tr>
            <td>{{ $r->code }}</td>
            <td>{{ $r->pays }}</td>
            <td class="num">{{ number_format($r->cle_repartition, 2, ',', ' ') }}</td>
            <td class="num">{{ $fmt($r->appele) }}</td>
            <td class="num">{{ $fmt($r->recouvre) }}</td>
            <td class="num {{ $r->arriere > 0 ? 'retard' : '' }}">{{ $fmt($r->arriere) }}</td>
            <td class="num">{{ $r->taux }}%</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3">TOTAL</td>
            <td class="num">{{ $fmt($synthese['appele']) }}</td>
            <td class="num">{{ $fmt($synthese['recouvre']) }}</td>
            <td class="num">{{ $fmt($synthese['arriere']) }}</td>
            <td class="num">{{ $synthese['taux'] }}%</td>
        </tr>
    </tfoot>
</table>
</body>
</html>
