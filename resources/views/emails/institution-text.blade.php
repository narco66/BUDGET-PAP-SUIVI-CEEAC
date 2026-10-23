CEEAC — GEST-BUDGET
=====================

{{ $corpsTexte ?? \App\Support\SafeHtml::text($corpsHtml) }}

@if(!empty($url))
Accéder : {{ $url }}
@endif

---
Message automatique — ne pas répondre.
