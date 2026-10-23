<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px}h1{color:#0a4a8f}
table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:5px}
</style></head><body>
<div style="text-align:center"><strong>CEEAC — Certificat de signature électronique</strong></div>
<h1>{{ $document->reference }}</h1>
<p><strong>Document signé :</strong> {{ $document->est_signe ? 'Oui' : 'Non' }}</p>
<table><thead><tr><th>Signataire</th><th>Date</th><th>Statut</th></tr></thead>
<tbody>@foreach($document->signatures->where('statut','signe') as $s)
<tr><td>{{ $s->signataire?->name }} ({{ $s->signataire?->email }})</td>
<td>{{ $s->date_signature?->format('d/m/Y H:i') }}</td><td>{{ $s->statut }}</td></tr>
@endforeach</tbody></table>
<p><strong>Hash document :</strong> {{ $document->hash_sha256 }}</p>
<p>Provider : {{ $verification['provider'] ?? 'interne' }}</p>
@if(!empty($verification['niveau']))
<p>Niveau : {{ \App\Enums\Ged\SignatureNiveau::tryFrom($verification['niveau'])?->libelle() ?? $verification['niveau'] }}</p>
@endif
</body></html>
