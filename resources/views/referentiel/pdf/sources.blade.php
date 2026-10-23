<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Sources de financement</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
    h1 { font-size: 14px; color: #0a4a8f; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #ddd; padding: 3px 4px; }
    th { background: #f0f4f8; }
</style></head>
<body>
<h1>Catalogue des sources de financement — CEEAC</h1>
<p>Généré le {{ now()->format('d/m/Y H:i') }} · {{ $sources->count() }} source(s)</p>
<table>
    <thead><tr><th>Code</th><th>Libellé</th><th>Type</th><th>Classification</th><th>PTF</th><th>Actif</th></tr></thead>
    <tbody>
    @foreach($sources as $s)
        <tr>
            <td>{{ $s->code }}</td>
            <td>{{ $s->libelle }}</td>
            <td>{{ \App\Models\SourceFinancement::TYPES[$s->type] ?? $s->type }}</td>
            <td>{{ $s->classification ? (\App\Models\SourceFinancement::CLASSIFICATIONS[$s->classification] ?? $s->classification) : '—' }}</td>
            <td>{{ $s->partenaire?->sigle ?? '—' }}</td>
            <td>{{ $s->actif ? 'Oui' : 'Non' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
