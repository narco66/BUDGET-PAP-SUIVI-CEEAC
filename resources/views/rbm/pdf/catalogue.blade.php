<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Catalogue {{ $label }} {{ $exercice->annee }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px}h1{font-size:14px;color:#0a4a8f}
table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border:1px solid #ccc;padding:4px}th{background:#eef2f7}
</style></head><body>
<h1>Catalogue {{ strtoupper($label) }} — Exercice {{ $exercice->annee }}</h1>
<table><thead><tr><th>Code</th><th>Intitulé</th><th>Contexte</th><th>Statut</th></tr></thead>
<tbody>@forelse($elements as $e)<tr>
<td>{{ $e['code'] }}</td><td>{{ $e['intitule'] }}</td><td>{{ $e['contexte'] ?? '—' }}</td><td>{{ $e['statut'] ?? '—' }}</td>
</tr>@empty<tr><td colspan="4">Aucun élément.</td></tr>@endforelse</tbody></table></body></html>
