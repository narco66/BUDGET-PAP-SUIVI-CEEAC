<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px}h1{color:#0a4a8f;font-size:15px}
.box{border:2px solid #0a4a8f;padding:16px;margin:20px 0;text-align:center}
</style></head><body>
<div style="text-align:center"><strong>CEEAC — Certificat d'intégrité numérique</strong></div>
<h1>{{ $document->reference }}</h1>
<p><strong>Titre :</strong> {{ $document->titre }}</p>
<p><strong>Empreinte SHA-256 :</strong><br><code>{{ $document->hash_sha256 }}</code></p>
<div class="box"><strong>{{ $integrite ? 'DOCUMENT INTÈGRE' : 'INTÉGRITÉ NON VÉRIFIÉE' }}</strong></div>
<p>Vérification effectuée le {{ now()->format('d/m/Y H:i:s') }} (UTC {{ now()->utc()->format('H:i') }})</p>
<p>UUID : {{ $document->uuid }}</p>
</body></html>
