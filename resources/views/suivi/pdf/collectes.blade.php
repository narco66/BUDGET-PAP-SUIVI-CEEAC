<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Collectes {{ $campagne->periode }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
    h1 { font-size: 14px; color: #0a4a8f; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #ccc; padding: 3px; }
    th { background: #0a4a8f; color: #fff; }
</style></head>
<body>
<h1>Rapport de suivi physique — {{ $campagne->libelle }}</h1>
<p>Période : {{ $campagne->periode }} · Exercice {{ $campagne->exercice?->annee }} · Généré le {{ now()->format('d/m/Y H:i') }}</p>
<table>
    <thead><tr>
        <th>Activité</th><th>Taux phys.</th><th>Qté prévue</th><th>Qté réalisée</th><th>Montant</th><th>Écart</th><th>Statut</th>
    </tr></thead>
    <tbody>
    @foreach($collectes as $c)
        <tr>
            <td>{{ $c->activite?->code }}</td>
            <td>{{ $c->taux_physique }}%</td>
            <td>{{ $c->quantite_prevue ?? '—' }}</td>
            <td>{{ $c->quantite_realisee ?? '—' }}</td>
            <td>{{ number_format((float)$c->montant_realise, 0, ',', ' ') }}</td>
            <td>{{ $c->ecart }}</td>
            <td>{{ $c->statut }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
