<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}h1{color:#0a4a8f}</style></head><body>
<div style="text-align:center"><strong>Bordereau de dépôt documentaire</strong></div>
<h1>{{ $document->reference }}</h1>
<p>Titre : {{ $document->titre }}</p>
<p>Fichier : {{ $document->nom_original }} ({{ number_format($document->taille/1024,0) }} Ko)</p>
<p>Déposé le : {{ $document->date_depot?->format('d/m/Y H:i') }}</p>
<p>Par : {{ $document->createur?->name }}</p>
<p>Hash SHA-256 : {{ $document->hash_sha256 }}</p>
</body></html>
