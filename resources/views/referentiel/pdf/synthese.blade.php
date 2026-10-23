<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Référentiel financier {{ $exercice?->annee ?? '' }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    h1 { font-size: 16px; color: #0a4a8f; margin-bottom: 4px; }
    h2 { font-size: 12px; margin-top: 14px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { border: 1px solid #ddd; padding: 3px 5px; text-align: left; }
    th { background: #f0f4f8; }
    .meta { color: #666; font-size: 9px; }
    .kpi { margin: 8px 0; }
    .kpi span { display: inline-block; margin-right: 16px; }
</style></head>
<body>
<h1>Référentiel financier — Commission de la CEEAC</h1>
<p class="meta">Exercice {{ $exercice?->annee ?? 'tous' }} · Généré le {{ now()->format('d/m/Y H:i') }}</p>

<div class="kpi">
    <span><strong>Exercices :</strong> {{ $stats['comptes']['exercices'] }}</span>
    <span><strong>Sources :</strong> {{ $stats['comptes']['sources_actives'] }}</span>
    <span><strong>PTF :</strong> {{ $stats['comptes']['partenaires_actifs'] }}</span>
    <span><strong>Conventions :</strong> {{ $stats['comptes']['conventions_actives'] }}</span>
    <span><strong>Lignes budget :</strong> {{ $stats['comptes']['lignes_budget'] }}</span>
    <span><strong>Anomalies audit :</strong> {{ $stats['audit']['anomalies_total'] ?? 0 }}</span>
</div>

<h2>Sources de financement (extrait)</h2>
<table>
    <thead><tr><th>Code</th><th>Libellé</th><th>Type</th><th>PTF</th></tr></thead>
    <tbody>
    @foreach($sources as $s)
        <tr>
            <td>{{ $s->code }}</td>
            <td>{{ $s->libelle }}</td>
            <td>{{ \App\Models\SourceFinancement::TYPES[$s->type] ?? $s->type }}</td>
            <td>{{ $s->partenaire?->sigle ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>Partenaires techniques et financiers (extrait)</h2>
<table>
    <thead><tr><th>Code</th><th>Libellé</th><th>Type</th><th>Sources</th><th>Conventions</th></tr></thead>
    <tbody>
    @foreach($partenaires as $p)
        <tr>
            <td>{{ $p->code }}</td>
            <td>{{ $p->libelle }}</td>
            <td>{{ \App\Models\PartenaireFinancier::TYPES[$p->type] ?? $p->type }}</td>
            <td>{{ $p->sources_count ?? 0 }}</td>
            <td>{{ $p->conventions_count ?? 0 }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
