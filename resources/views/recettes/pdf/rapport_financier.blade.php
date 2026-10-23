<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Rapport financier des recettes</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
    .entete { color: #0a4a8f; }
    .entete .inst { font-weight: bold; font-size: 11px; }
    h1 { font-size: 15px; color: #0a4a8f; margin: 6px 0 2px; }
    h2 { font-size: 12px; color: #0a4a8f; border-bottom: 1px solid #0a4a8f; padding-bottom: 2px; margin-top: 14px; }
    .meta { color: #777; font-size: 8px; margin-bottom: 6px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { border: 1px solid #ddd; padding: 3px 4px; }
    th { background: #0a4a8f; color: #fff; }
    td.num, th.num { text-align: right; }
    .kpi td { border: none; padding: 2px 4px; }
    .kpi .lbl { color: #555; }
    .kpi .val { font-weight: bold; text-align: right; }
    tfoot td { font-weight: bold; background: #eef3f9; }
    .retard { color: #b00020; }
</style></head>
<body>
@php $fmt = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp
<div class="entete">
    <div class="inst">COMMISSION DE LA CEEAC</div>
    <div style="font-style:italic;font-size:8px;color:#666;">Communauté Économique des États de l'Afrique Centrale</div>
</div>
<h1>Rapport financier des recettes</h1>
<div class="meta">Exercice {{ $exercice?->annee ?? 'global' }} · Réf. RAP-REC-{{ $exercice?->annee ?? 'GLOBAL' }} · généré le {{ now()->format('d/m/Y H:i') }}</div>

<h2>1. Synthèse</h2>
<table class="kpi">
    <tr><td class="lbl">Recettes prévues</td><td class="val">{{ $fmt($synthesePrev['montant_prevu']) }} XAF</td>
        <td class="lbl">Recettes réalisées</td><td class="val">{{ $fmt($synthesePrev['montant_realise']) }} XAF</td></tr>
    <tr><td class="lbl">Contributions appelées</td><td class="val">{{ $fmt($synthMob['appele']) }} XAF</td>
        <td class="lbl">Contributions recouvrées</td><td class="val">{{ $fmt($synthMob['recouvre']) }} XAF</td></tr>
    <tr><td class="lbl">Arriérés de contributions</td><td class="val">{{ $fmt($synthMob['arriere']) }} XAF</td>
        <td class="lbl">Taux de recouvrement</td><td class="val">{{ $synthMob['taux'] }} %</td></tr>
</table>

<h2>2. Mobilisation des contributions par État membre</h2>
<table>
    <thead><tr><th>État</th><th class="num">Appelé</th><th class="num">Recouvré</th><th class="num">Arriéré</th><th class="num">Taux</th></tr></thead>
    <tbody>
    @foreach ($mobilisation as $r)
        <tr>
            <td>{{ $r->pays }}</td>
            <td class="num">{{ $fmt($r->appele) }}</td>
            <td class="num">{{ $fmt($r->recouvre) }}</td>
            <td class="num {{ $r->arriere > 0 ? 'retard' : '' }}">{{ $fmt($r->arriere) }}</td>
            <td class="num">{{ $r->taux }}%</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot><tr><td>TOTAL</td><td class="num">{{ $fmt($synthMob['appele']) }}</td><td class="num">{{ $fmt($synthMob['recouvre']) }}</td><td class="num">{{ $fmt($synthMob['arriere']) }}</td><td class="num">{{ $synthMob['taux'] }}%</td></tr></tfoot>
</table>

<h2>3. État des arriérés</h2>
@if ($arrieres->isEmpty())
    <p style="color:#777;">Aucun arriéré recensé.</p>
@else
<table>
    <thead><tr><th>Référence</th><th>Débiteur</th><th>Échéance</th><th class="num">Arriéré</th><th class="num">Pénalité</th></tr></thead>
    <tbody>
    @foreach ($arrieres as $a)
        <tr>
            <td>{{ $a->reference }}</td>
            <td>{{ $a->etatMembre?->pays ?? $a->sourceFinancement?->libelle ?? '—' }}</td>
            <td>{{ $a->date_echeance?->format('d/m/Y') ?? '—' }}@if($a->enRetard()) <span class="retard">⚠</span>@endif</td>
            <td class="num">{{ $fmt($a->solde) }}</td>
            <td class="num">{{ $fmt($a->penalite) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif
</body>
</html>
