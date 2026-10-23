<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Structure du budget {{ $exercice->annee }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
    h1 { font-size: 15px; color: #0a4a8f; margin-bottom: 2px; }
    h2 { font-size: 11px; margin-top: 12px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
    table { width: 100%; border-collapse: collapse; margin-top: 5px; }
    th, td { border: 1px solid #ddd; padding: 2px 4px; text-align: left; }
    th { background: #f0f4f8; font-size: 8px; }
    .meta { color: #666; font-size: 8px; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .kpi span { display: inline-block; margin-right: 14px; }
</style></head>
<body>
<h1>Structure du budget — Commission de la CEEAC</h1>
<p class="meta">Exercice {{ $exercice->annee }} · {{ $exercice->libelle }} · Généré le {{ now()->format('d/m/Y H:i') }}</p>

<div class="kpi">
    <span><strong>Lignes :</strong> {{ $synthese['nb_lignes'] }}</span>
    <span><strong>Structures :</strong> {{ $synthese['nb_structures'] }}</span>
    <span><strong>Ouvert :</strong> {{ number_format($synthese['ouvert'], 0, ',', ' ') }} XAF</span>
    <span><strong>Engagé :</strong> {{ number_format($synthese['engage'], 0, ',', ' ') }} XAF</span>
    <span><strong>Payé :</strong> {{ number_format($synthese['paye'], 0, ',', ' ') }} XAF</span>
</div>

<h2>Synthèse par département</h2>
<table>
    <thead><tr><th>Code</th><th>Libellé</th><th class="num">Ouvert</th><th class="num">Engagé</th><th class="num">Lignes</th></tr></thead>
    <tbody>
    @forelse($parDepartement as $d)
        <tr>
            <td>{{ $d['code'] }}</td><td>{{ $d['libelle'] }}</td>
            <td class="num">{{ number_format($d['ouvert'], 0, ',', ' ') }}</td>
            <td class="num">{{ number_format($d['engage'], 0, ',', ' ') }}</td>
            <td class="num">{{ $d['nb_lignes'] }}</td>
        </tr>
    @empty
        <tr><td colspan="5">Aucune donnée.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Détail des lignes budgétaires
    @if($nbLignesTotal > 300)
        ({{ $lignes->count() }} / {{ $nbLignesTotal }} affichées)
    @endif
</h2>
<table>
    <thead><tr>
        <th>Code</th><th>Intitulé</th><th>Structure</th><th>Rubrique</th><th>Activité</th>
        <th class="num">Ouvert</th><th class="num">Engagé</th><th class="num">Payé</th>
    </tr></thead>
    <tbody>
    @foreach($lignes as $l)
        <tr>
            <td>{{ $l->code }}</td>
            <td>{{ \Illuminate\Support\Str::limit($l->intitule, 35) }}</td>
            <td>{{ \Illuminate\Support\Str::limit($l->structure_libelle, 20) }}</td>
            <td>{{ $l->rubrique_code }}</td>
            <td>{{ \Illuminate\Support\Str::limit($l->activite_intitule, 22) }}</td>
            <td class="num">{{ number_format((float) $l->montant_ouvert, 0, ',', ' ') }}</td>
            <td class="num">{{ number_format((float) $l->montant_engage, 0, ',', ' ') }}</td>
            <td class="num">{{ number_format((float) $l->montant_paye, 0, ',', ' ') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
