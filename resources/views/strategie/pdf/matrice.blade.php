<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Matrice alignement</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:9px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:3px}th{background:#eee}.ok{background:#e8f5e9}</style></head>
<body><h1>Matrice d'alignement stratégique CEEAC</h1>
<p>Taux : {{ $synthese['taux_matrice'] }}% · Généré le {{ now()->format('d/m/Y H:i') }}</p>
<table><thead><tr><th>Vision</th><th>Orientation</th><th>Programme</th><th>Projet</th><th>Activité</th><th>Pilier</th><th>Budget</th><th>Aligné</th></tr></thead>
<tbody>@foreach($lignes as $l)<tr class="{{ $l['aligne'] ? 'ok' : '' }}">
<td>{{ $l['vision'] }}</td><td>{{ $l['orientation'] }}</td><td>{{ $l['programme'] }}</td><td>{{ $l['projet'] }}</td>
<td>{{ $l['activite'] }}</td><td>{{ $l['pilier_rbm'] }}</td><td>{{ number_format($l['budget'], 0, ',', ' ') }}</td><td>{{ $l['aligne'] ? 'Oui' : 'Non' }}</td>
</tr>@endforeach</tbody></table></body></html>
