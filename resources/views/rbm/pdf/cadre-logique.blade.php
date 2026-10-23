<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Cadre logique {{ $exercice->annee }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#222}
h1{font-size:14px;color:#0a4a8f;margin-bottom:4px}
.meta{color:#666;font-size:8px;margin-bottom:10px}
h2{font-size:11px;margin:12px 0 6px;border-bottom:1px solid #ccc;padding-bottom:3px}
table{width:100%;border-collapse:collapse;margin-top:4px}
th,td{border:1px solid #ddd;padding:4px 5px;vertical-align:top}
th{background:#f0f4f8}
</style></head><body>
<h1>{{ $document->intitule ?? 'Cadre logique '.$exercice->annee }}</h1>
<p class="meta">Commission de la CEEAC · Exercice {{ $exercice->annee }} · Généré le {{ now()->format('d/m/Y H:i') }}</p>
@if(!empty($document->description))
<p>{{ $document->description }}</p>
@endif

@foreach($cadre as $bloc)
<h2>{{ $bloc['pilier']['code'] }} — {{ $bloc['pilier']['intitule'] }}</h2>
<table>
    <thead><tr><th>Axe</th><th>Objectif stratégique</th><th>Commentaire</th><th>Indicateurs IOV</th></tr></thead>
    <tbody>
    @foreach($bloc['objectifs'] as $obj)
        <tr>
            <td><strong>{{ $obj['axe']['code'] }}</strong><br>{{ $obj['axe']['intitule'] }}</td>
            <td>{{ $obj['objectif_strategique'] ?? '—' }}</td>
            <td>{{ $obj['commentaire'] ?? '—' }}</td>
            <td>{{ collect($obj['indicateurs'])->pluck('code')->implode(', ') ?: '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endforeach
</body></html>
