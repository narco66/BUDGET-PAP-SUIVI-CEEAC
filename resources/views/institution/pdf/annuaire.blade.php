<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Annuaire institutionnel CEEAC</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; vertical-align: top; }
        th { background: #eef2f7; text-align: left; }
        tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <h1>Annuaire institutionnel — Commission de la CEEAC</h1>
    <div class="meta">
        Genere le {{ $genereLe->format('d/m/Y H:i') }}
        @if($filtreType) · Type : {{ $filtreType }} @endif
        @if($filtreQ) · Recherche : {{ $filtreQ }} @endif
        · {{ $lignes->count() }} structure(s)
    </div>

    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Libelle</th>
                <th>Type</th>
                <th>Departement</th>
                <th>Direction</th>
                <th>Service</th>
                <th>Responsable</th>
                <th>Actif</th>
            </tr>
        </thead>
        <tbody>
        @forelse($lignes as $ligne)
            <tr>
                <td>{{ $ligne['code'] }}</td>
                <td>{{ $ligne['libelle'] }} @if($ligne['sigle'] !== '—') ({{ $ligne['sigle'] }}) @endif</td>
                <td>{{ $ligne['type'] }}</td>
                <td>{{ $ligne['departement'] }}</td>
                <td>{{ $ligne['direction'] }}</td>
                <td>{{ $ligne['service'] }}</td>
                <td>{{ $ligne['responsable'] }}</td>
                <td>{{ $ligne['actif'] }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;">Aucune structure.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
