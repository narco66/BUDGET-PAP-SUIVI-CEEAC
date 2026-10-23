<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>{{ $sujetMail ?? 'CEEAC' }}</title></head>
<body style="font-family:Arial,sans-serif;color:#1a1a1a;line-height:1.5;margin:0;padding:0;background:#f4f6f9;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
<tr><td style="background:#0a4a8f;color:#fff;padding:20px 24px;">
<strong style="font-size:18px;">CEEAC — GEST-BUDGET</strong><br>
<small style="opacity:.85;">Commission Économique des États de l'Afrique Centrale</small>
</td></tr>
<tr><td style="padding:24px;">
{{ \App\Support\SafeHtml::render($corpsHtml) }}
@if(!empty($url))
<p style="margin-top:24px;">
<a href="{{ $url }}" style="background:#0a4a8f;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px;display:inline-block;">Accéder à l'application</a>
</p>
@endif
</td></tr>
<tr><td style="background:#eef2f7;padding:12px 24px;font-size:12px;color:#666;">
Message automatique — ne pas répondre directement à cet e-mail.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
