<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Indicateurs {{ $exercice->annee }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px}h1{font-size:14px;color:#0a4a8f}
table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #ddd;padding:3px}th{background:#f0f4f8}
</style></head><body>
<h1>Catalogue des indicateurs — Exercice {{ $exercice->annee }}</h1>
<table><thead><tr><th>Code</th><th>Libellé</th><th>Type</th><th>Unité</th><th>Périodicité</th><th>Cible</th><th>Contexte</th></tr></thead>
<tbody>@foreach($indicateurs as $i)<tr>
<td>{{ $i['code'] }}</td><td>{{ $i['libelle'] }}</td><td>{{ $i['type'] }}</td><td>{{ $i['unite'] ?? '—' }}</td>
<td>{{ $i['periodicite'] }}</td><td>{{ $i['cible'] ?? '—' }}</td><td>{{ $i['contexte'] }}</td>
</tr>@endforeach</tbody></table></body></html>
