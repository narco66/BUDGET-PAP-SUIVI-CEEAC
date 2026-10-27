<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Tableau pilotage stratégique</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}h1{font-size:14px}table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #ccc;padding:4px}.muted{color:#777}</style>
</head><body>
@php $pct = fn ($v) => $v === null ? 'non évaluable' : number_format((float) $v, 1, ',', ' ').' %'; @endphp
<h1>Tableau de pilotage décisionnel — CEEAC</h1>
<p>Exercice {{ $tableau['exercice']?->annee ?? '—' }} · Score de pilotage {{ ($tableau['score_pilotage_evaluable'] ?? true) ? $pct($tableau['score_pilotage']) : 'non évaluable' }} · {{ \App\Support\Horodatage::local(now(), 'd/m/Y H:i') }}</p>
@if ($tableau['referentiel_vide'] ?? false)
<p><strong>Référentiel stratégique non renseigné</strong> (aucune vision, aucun plan, programme, projet ni objectif) : les indicateurs d'alignement ne sont pas évaluables.</p>
@endif
<table><tr><th>Composante du score</th><th>Valeur</th><th>Base</th></tr>
@foreach ($tableau['score_pilotage_detail'] ?? [] as $c)
<tr><td>{{ $c['libelle'] }}</td><td>{{ $pct($c['taux']) }}</td><td class="muted">{{ $c['base'] }}</td></tr>
@endforeach
<tr><td>Taux financier du PAP</td><td>{{ $pct($tableau['reconciliation']['execution_pap']['taux_financier'] ?? null) }}</td><td class="muted">{{ $tableau['reconciliation']['execution_pap']['activites'] ?? 0 }} activité(s)</td></tr>
</table>
@if ($tableau['alertes'])
<p><strong>Alertes :</strong></p>
<ul>@foreach ($tableau['alertes'] as $a)<li>{{ is_array($a) ? $a['message'] : $a }}</li>@endforeach</ul>
@endif
</body></html>
