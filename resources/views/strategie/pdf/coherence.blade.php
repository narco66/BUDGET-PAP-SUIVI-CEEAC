<!DOCTYPE html>

<html lang="fr"><head><meta charset="utf-8"><title>Rapport cohérence stratégique</title>

<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#222}h1{font-size:16px}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:5px;text-align:left}th{background:#f0f0f0}.score{font-size:22px;font-weight:bold;color:#0d6efd}</style>

</head><body>

<h1>Rapport de cohérence stratégique — CEEAC</h1>

<p>Généré le {{ now()->format('d/m/Y H:i') }}@if($rapport['plan_id']) · Plan filtré @endif</p>

<p class="score">Score global : {{ $rapport['score_global'] }}%</p>

<ul>

    <li>Objectifs : {{ $rapport['objectifs_total'] }} ({{ $rapport['objectifs_sans_indicateur']->count() }} sans indicateur)</li>

    <li>Indicateurs sans cible : {{ $rapport['indicateurs_sans_cible']->count() }}</li>

    <li>Risques sans mitigation : {{ $rapport['risques_sans_mitigation']->count() }}</li>

    <li>Taux matrice alignement : {{ $rapport['alignement']['taux_matrice'] }}%</li>

</ul>

</body></html>

