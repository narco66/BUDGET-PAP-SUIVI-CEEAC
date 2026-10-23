<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Matrice RBM {{ $exercice->annee }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px}h1{font-size:14px;color:#0a4a8f}
table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #ddd;padding:3px 4px}th{background:#f0f4f8}
</style></head><body>
<h1>Matrice RBM/GAR — Exercice {{ $exercice->annee }}</h1>
<p>Généré le {{ now()->format('d/m/Y H:i') }}</p>
<table><thead><tr><th>Pilier</th><th>Axe</th><th>Produit</th><th>Sous-pr.</th><th>Activité</th><th>Intitulé</th><th>Montant</th></tr></thead>
<tbody>@foreach($lignes as $l)<tr>
<td>{{ $l['pilier'] }}</td><td>{{ $l['axe'] }}</td><td>{{ $l['produit'] }}</td><td>{{ $l['sous_produit'] }}</td>
<td>{{ $l['activite'] }}</td><td>{{ \Illuminate\Support\Str::limit($l['intitule'],40) }}</td><td>{{ number_format($l['montant'],0,',',' ') }}</td>
</tr>@endforeach</tbody></table></body></html>
