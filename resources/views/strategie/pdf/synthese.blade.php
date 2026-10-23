<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Synthèse planification stratégique</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:4px 6px}th{background:#f0f0f0}h1{font-size:16px}h2{font-size:13px;margin-top:18px}</style>
</head>
<body>
<h1>CEEAC — Synthèse planification stratégique</h1>
<p>Généré le {{ now()->format('d/m/Y H:i') }}</p>
<ul>
    <li>Visions actives : {{ $synthese['visions_actives'] }}</li>
    <li>Plans actifs : {{ $synthese['plans_actifs'] }}</li>
    <li>Programmes : {{ $synthese['programmes'] }} · Projets : {{ $synthese['projets'] }}</li>
    <li>Budget programmé (programmes) : {{ number_format($synthese['budget_programme'], 0, ',', ' ') }} XAF</li>
</ul>
<h2>Plans stratégiques</h2>
<table><thead><tr><th>Réf.</th><th>Intitulé</th><th>Vision</th><th>Statut</th></tr></thead>
<tbody>@foreach($plans as $p)<tr><td>{{ $p->reference }}</td><td>{{ $p->intitule }}</td><td>{{ $p->vision?->reference }}</td><td>{{ $p->statut }}</td></tr>@endforeach</tbody></table>
<h2>Programmes stratégiques</h2>
<table><thead><tr><th>Code</th><th>Intitulé</th><th>Orientation</th><th>Budget</th></tr></thead>
<tbody>@foreach($programmes as $p)<tr><td>{{ $p->code }}</td><td>{{ $p->intitule }}</td><td>{{ $p->orientationStrategique?->code }}</td><td>{{ number_format($p->budget_previsionnel, 0, ',', ' ') }}</td></tr>@endforeach</tbody></table>
</body></html>
