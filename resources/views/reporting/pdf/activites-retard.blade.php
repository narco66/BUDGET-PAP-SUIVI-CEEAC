<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Activités en retard</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #222; }
    h1 { font-size: 14px; color: #0a4a8f; margin: 0 0 2px; }
    .meta { color: #777; font-size: 8px; margin-bottom: 8px; }
    .kpi { margin: 6px 0; }
    .kpi span { display: inline-block; margin-right: 18px; }
    .kpi b { color: #0a4a8f; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { border: 1px solid #ddd; padding: 2px 3px; }
    th { background: #0a4a8f; color: #fff; }
    td.num { text-align: right; }
    .j1 { background: #fff3cd; } .j2 { background: #ffe0b2; } .j3 { background: #f8d7da; }
</style></head>
<body>
@php $fr = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp
<h1>Activités du PAP en retard — CEEAC</h1>
<div class="meta">Exercice {{ $exercice->annee }} · généré le {{ now()->format('d/m/Y H:i') }}</div>
<div class="kpi">
    <span>Activités en retard : <b>{{ $synthese['total'] }}</b></span>
    <span>Budget concerné : <b>{{ $fr($synthese['montant']) }} XAF</b></span>
    <span>Avancement moyen : <b>{{ $synthese['taux_moyen'] }}%</b></span>
</div>

<table>
    <thead><tr>
        <th>Code</th><th>Activité</th><th>Pilier</th><th>Axe</th><th>Département</th><th>Responsable</th>
        <th>Fin prévue</th><th class="num">Retard</th><th class="num">Phys.</th><th class="num">Budget</th><th class="num">Consommé</th>
    </tr></thead>
    <tbody>
    @foreach ($activites as $a)
        @php $j = $svc->joursRetard($a); $niv = $svc->niveau($j); $cls = ['warning'=>'j1','orange'=>'j2','danger'=>'j3'][$niv]; @endphp
        <tr class="{{ $cls }}">
            <td>{{ $a->code }}</td>
            <td>{{ \Illuminate\Support\Str::limit($a->intitule, 45) }}</td>
            <td>{{ $a->produit?->axe?->pilier?->code ?? '—' }}</td>
            <td>{{ $a->produit?->axe?->code ?? '—' }}</td>
            <td>{{ $a->structure?->sigle ?? $a->structure?->libelle ?? '—' }}</td>
            <td>{{ $a->responsable?->name ?? '—' }}</td>
            <td>{{ $a->date_fin_prevue ? \Illuminate\Support\Carbon::parse($a->date_fin_prevue)->format('d/m/Y') : '—' }}</td>
            <td class="num">{{ $j }} j</td>
            <td class="num">{{ (float) $a->taux_realisation_physique }}%</td>
            <td class="num">{{ $fr($a->montant_total) }}</td>
            <td class="num">{{ $fr($svc->budgetConsomme($a)) }}</td>
        </tr>
    @endforeach
    @if ($activites->isEmpty())
        <tr><td colspan="11" style="text-align:center;color:#777;">Aucune activité en retard.</td></tr>
    @endif
    </tbody>
</table>
</body>
</html>
