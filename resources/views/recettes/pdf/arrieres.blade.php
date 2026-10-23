<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>État des arriérés</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #222; }
    h1 { font-size: 15px; color: #0a4a8f; margin-bottom: 2px; }
    .meta { color: #666; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #ddd; padding: 3px 4px; }
    th { background: #0a4a8f; color: #fff; }
    td.num { text-align: right; }
    tfoot td { font-weight: bold; background: #f0f4f8; }
</style></head>
<body>
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp
<h1>État des arriérés de contributions — CEEAC</h1>
<div class="meta">Exercice {{ $exercice?->annee ?? 'tous' }} · Généré le {{ now()->format('d/m/Y H:i') }} · {{ $appels->count() }} appel(s) avec solde</div>

<table>
    <thead>
        <tr><th>Référence</th><th>Débiteur</th><th>Objet</th><th>Échéance</th><th>Appelé</th><th>Recouvré</th><th>Arriéré</th><th>Pénalité</th></tr>
    </thead>
    <tbody>
    @foreach ($appels as $a)
        <tr>
            <td>{{ $a->reference }}</td>
            <td>{{ $a->etatMembre?->pays ?? $a->sourceFinancement?->libelle ?? '—' }}</td>
            <td>{{ $a->objet }}</td>
            <td>{{ $a->date_echeance?->format('d/m/Y') ?? '—' }}@if($a->enRetard()) ⚠@endif</td>
            <td class="num">{{ $fmt($a->montant_appele) }}</td>
            <td class="num">{{ $fmt($a->montant_regle) }}</td>
            <td class="num">{{ $fmt($a->solde) }}</td>
            <td class="num">{{ $fmt($a->penalite) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6">TOTAL ARRIÉRÉS</td>
            <td class="num">{{ $fmt($appels->sum(fn($a) => $a->solde)) }}</td>
            <td class="num">{{ $fmt($appels->sum(fn($a) => $a->penalite)) }}</td>
        </tr>
    </tfoot>
</table>
</body>
</html>
