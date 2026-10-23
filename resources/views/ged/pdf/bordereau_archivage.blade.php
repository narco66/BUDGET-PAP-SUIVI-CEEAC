<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}h1{color:#0a4a8f}</style></head><body>
<div style="text-align:center"><strong>Bordereau d'archivage électronique</strong></div>
<h1>{{ $document->reference }}</h1>
<p>Titre : {{ $document->titre }}</p>
<p>Archivé le : {{ $document->date_archivage?->format('d/m/Y H:i') }}</p>
@php $archive = $document->archives->sortByDesc('date_archivage')->first(); @endphp
@if($archive)
<p>Motif : {{ $archive->motif }}</p>
<p>Par : {{ $archive->archiveur?->name }}</p>
@endif
<p>Document verrouillé en lecture seule.</p>
</body></html>
