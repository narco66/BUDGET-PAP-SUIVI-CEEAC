<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Rapport sécurité API</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}h1{font-size:16px}table{width:100%;border-collapse:collapse;margin-top:12px}td,th{border:1px solid #ccc;padding:6px;text-align:left}th{background:#f0f0f0}</style>
</head>
<body>
<h1>Rapport sécurité API — CEEAC</h1>
<p>Généré le {{ \Illuminate\Support\Carbon::parse($rapport['genere_le'])->format('d/m/Y H:i') }} — période {{ $rapport['periode_jours'] }} jours</p>
<h2>Indicateurs</h2>
<table>
@foreach($rapport['kpis'] as $cle => $val)
<tr><th>{{ $cle }}</th><td>{{ is_array($val) ? json_encode($val) : $val }}</td></tr>
@endforeach
<tr><th>Rejets rate-limit (429)</th><td>{{ $rapport['rejets_rate_limit'] }}</td></tr>
<tr><th>Applications actives</th><td>{{ $rapport['apps_actives'] }}</td></tr>
<tr><th>Webhooks actifs</th><td>{{ $rapport['webhooks_actifs'] }}</td></tr>
</table>
@if($rapport['top_erreurs']->isNotEmpty())
<h2>Top endpoints en erreur</h2>
<table><tr><th>Chemin</th><th>Occurrences</th></tr>
@foreach($rapport['top_erreurs'] as $e)<tr><td>{{ $e->chemin }}</td><td>{{ $e->total }}</td></tr>@endforeach
</table>
@endif
</body>
</html>
