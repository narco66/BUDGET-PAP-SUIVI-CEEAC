<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Partenaires PTF</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
    h1 { font-size: 14px; color: #0a4a8f; }
    h2 { font-size: 11px; margin-top: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { border: 1px solid #ddd; padding: 3px 4px; }
    th { background: #f0f4f8; }
</style></head>
<body>
<h1>Partenaires techniques et financiers — CEEAC</h1>
<p>Exercice {{ $exercice?->annee ?? 'tous' }} · Généré le {{ now()->format('d/m/Y H:i') }}</p>

<h2>Partenaires ({{ $partenaires->count() }})</h2>
<table>
    <thead><tr><th>Code</th><th>Libellé</th><th>Sigle</th><th>Type</th><th>Sources</th><th>Conventions</th></tr></thead>
    <tbody>
    @foreach($partenaires as $p)
        <tr>
            <td>{{ $p->code }}</td><td>{{ $p->libelle }}</td><td>{{ $p->sigle ?? '—' }}</td>
            <td>{{ \App\Models\PartenaireFinancier::TYPES[$p->type] ?? $p->type }}</td>
            <td>{{ $p->sources_count ?? 0 }}</td><td>{{ $p->conventions_count ?? 0 }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<h2>Conventions de financement ({{ $conventions->count() }})</h2>
<table>
    <thead><tr><th>Code</th><th>Libellé</th><th>PTF</th><th>Montant accord</th><th>Engagé</th><th>Statut</th></tr></thead>
    <tbody>
    @foreach($conventions as $c)
        <tr>
            <td>{{ $c->code }}</td><td>{{ $c->libelle }}</td>
            <td>{{ $c->partenaire?->sigle ?? '—' }}</td>
            <td>{{ number_format($c->montant_accord, 0, ',', ' ') }}</td>
            <td>{{ number_format($c->montant_engage, 0, ',', ' ') }}</td>
            <td>{{ \App\Models\ConventionFinancement::STATUTS[$c->statut] ?? $c->statut }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
