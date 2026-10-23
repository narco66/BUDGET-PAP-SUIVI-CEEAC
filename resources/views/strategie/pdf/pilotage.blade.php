<!DOCTYPE html>

<html lang="fr"><head><meta charset="utf-8"><title>Tableau pilotage stratégique</title>

<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}h1{font-size:14px}table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #ccc;padding:4px}</style>

</head><body>

<h1>Tableau de pilotage décisionnel — CEEAC</h1>

<p>Exercice {{ $tableau['exercice']?->annee ?? '—' }} · Score {{ $tableau['score_pilotage'] }}% · {{ now()->format('d/m/Y') }}</p>

<table><tr><th>Indicateur</th><th>Valeur</th></tr>

<tr><td>Cohérence stratégique</td><td>{{ $tableau['coherence']['score_global'] }}%</td></tr>

<tr><td>Alignement matrice</td><td>{{ $tableau['alignement']['taux_matrice'] }}%</td></tr>

<tr><td>Liaison programmation ↔ PAP</td><td>{{ $tableau['reconciliation']['taux_liaison_prog'] }}%</td></tr>

<tr><td>Taux financier PAP</td><td>{{ $tableau['reconciliation']['execution_pap']['taux_financier'] ?? '—' }}%</td></tr>

</table>

@if($tableau['alertes'])<p><strong>Alertes :</strong> {{ implode(' · ', $tableau['alertes']) }}</p>@endif

</body></html>

