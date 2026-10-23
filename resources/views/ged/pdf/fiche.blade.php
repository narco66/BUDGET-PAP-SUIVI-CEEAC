<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#222}
h1{font-size:16px;color:#0a4a8f}table{width:100%;border-collapse:collapse;margin-top:12px}
td,th{border:1px solid #ccc;padding:6px;text-align:left}th{background:#f0f4f8}
.header{text-align:center;border-bottom:2px solid #0a4a8f;padding-bottom:8px;margin-bottom:16px}
</style></head><body>
<div class="header"><strong>COMMISSION DE LA CEEAC</strong><br>Fiche documentaire GED</div>
<h1>{{ $document->titre }}</h1>
<table>
<tr><th>Référence</th><td>{{ $document->reference }}</td><th>UUID</th><td>{{ $document->uuid }}</td></tr>
<tr><th>Statut</th><td>{{ $document->statut?->libelle() }}</td><th>Confidentialité</th><td>{{ $document->niveau_confidentialite?->libelle() }}</td></tr>
<tr><th>Catégorie</th><td>{{ $document->categorie?->libelle ?? '—' }}</td><th>Type</th><td>{{ $document->typeDocument?->libelle ?? '—' }}</td></tr>
<tr><th>Dépôt</th><td>{{ $document->date_depot?->format('d/m/Y H:i') }}</td><th>Version</th><td>v{{ $document->version_courante }}</td></tr>
<tr><th>Auteur</th><td>{{ $document->createur?->name }}</td><th>Module</th><td>{{ $document->module_source ?? '—' }}</td></tr>
<tr><th>SHA-256</th><td colspan="3"><code>{{ $document->hash_sha256 }}</code></td></tr>
<tr><th>Intégrité</th><td colspan="3">{{ $verification['integrite'] ? 'OK' : 'Compromis' }}</td></tr>
</table>
@if($document->description)<p><strong>Description :</strong> {{ $document->description }}</p>@endif
<p class="small" style="margin-top:24px">Document généré le {{ now()->format('d/m/Y H:i') }} · GEST-BUDGET-PAP CEEAC</p>
</body></html>
