<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Synthèse RBM {{ $exercice->annee }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
    h1 { font-size: 16px; color: #0a4a8f; margin-bottom: 4px; }
    h2 { font-size: 12px; margin-top: 16px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
    th { background: #f0f4f8; }
    .meta { color: #666; font-size: 9px; }
</style></head>
<body>
<h1>Synthèse institutionnelle RBM/GAR — Exercice {{ $exercice->annee }}</h1>
<p class="meta">Commission de la CEEAC · Généré le {{ now()->format('d/m/Y H:i') }}</p>

<h2>Matrice RBM/GAR (extrait)</h2>
<table>
    <thead><tr><th>Pilier</th><th>Axe</th><th>Activité</th><th>Intitulé</th><th>Montant</th></tr></thead>
    <tbody>
    @foreach($matrice->take(50) as $l)
        <tr>
            <td>{{ $l['pilier'] }}</td><td>{{ $l['axe'] }}</td><td>{{ $l['activite'] }}</td>
            <td>{{ \Illuminate\Support\Str::limit($l['intitule'], 50) }}</td>
            <td>{{ number_format($l['montant'], 0, ',', ' ') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="meta">{{ $matrice->count() }} ligne(s) au total.</p>
</body>
</html>
