<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Théorie du changement {{ $exercice->annee }}</title>
<style>
    @page { margin: 22px 26px 36px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #222; }
    h1 { font-size: 15px; color: #0a4a8f; margin: 0 0 2px; }
    .meta { color: #777; font-size: 8px; margin-bottom: 10px; }
    .impact { background: #0a4a8f; color: #fff; padding: 4px 7px; border-radius: 3px; font-weight: bold; font-size: 10px; margin-top: 12px; }
    .effet { border-left: 3px solid #0aa2c0; padding-left: 7px; margin: 7px 0 7px 6px; }
    .effet-t { font-weight: bold; color: #0a6b80; font-size: 9.5px; }
    .produit { margin: 3px 0 3px 14px; }
    .produit-t { font-weight: bold; color: #198754; }
    .activite { margin-left: 26px; color: #555; }
    .iov { color: #0a4a8f; font-size: 7.5px; margin: 2px 0; }
    .desc { color: #666; font-style: italic; margin: 2px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th, td { border: 1px solid #ddd; padding: 2px 4px; text-align: left; }
    th { background: #b8860b; color: #fff; font-weight: normal; }
    .hyp-h { font-size: 11px; color: #b8860b; border-bottom: 1px solid #b8860b; margin: 16px 0 4px; padding-bottom: 2px; }
    .vide { color: #999; font-style: italic; }
    .legende { font-size: 7.5px; color: #888; margin-bottom: 8px; }
</style></head>
<body>
@php $fmt = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp
<h1>Théorie du changement — CEEAC</h1>
<div class="meta">Exercice {{ $exercice->annee }} · logique causale GAR · généré le {{ now()->format('d/m/Y H:i') }}</div>
<div class="legende">Lecture ascendante de la chaîne de résultats : <b>Activités</b> → <b>Produits</b> → <b>Effets</b> → <b>Impact</b>.</div>

@forelse($tc['piliers'] as $pilier)
    <div class="impact">IMPACT · {{ $pilier['code'] }} — {{ $pilier['intitule'] }}</div>
    @if($pilier['description'])<div class="desc">{{ $pilier['description'] }}</div>@endif
    @foreach($pilier['indicateurs'] as $iov)
        <div class="iov">◆ Indicateur d’impact {{ $iov['code'] }} : {{ \Illuminate\Support\Str::limit($iov['libelle'], 70) }}@if($iov['valeur_cible'] !== null) (cible {{ $iov['valeur_cible'] }})@endif</div>
    @endforeach

    @forelse($pilier['axes'] as $axe)
    <div class="effet">
        <div class="effet-t">EFFET · {{ $axe['code'] }} — {{ $axe['intitule'] }}</div>
        @foreach($axe['indicateurs'] as $iov)
            <div class="iov">◆ {{ $iov['code'] }}@if($iov['valeur_cible'] !== null) — cible {{ $iov['valeur_cible'] }}@endif</div>
        @endforeach
        @forelse($axe['produits'] as $produit)
            <div class="produit"><span class="produit-t">PRODUIT · {{ $produit['code'] }}</span> — {{ $produit['intitule'] }}</div>
            @foreach($produit['activites'] as $act)
                <div class="activite">↳ Activité {{ $act['code'] }} — {{ \Illuminate\Support\Str::limit($act['intitule'], 70) }} ({{ $fmt($act['montant']) }} XAF)</div>
            @endforeach
        @empty
            <div class="produit vide">Aucun produit rattaché.</div>
        @endforelse
    </div>
    @empty
    <div class="effet vide">Aucun effet (axe) défini.</div>
    @endforelse
@empty
    <p class="vide">Aucun pilier RBM défini pour cet exercice.</p>
@endforelse

<div class="hyp-h">Hypothèses & conditions critiques (risques du PAP)</div>
@if(!empty($tc['hypotheses']))
<table>
    <thead><tr><th>Code</th><th>Hypothèse / risque</th><th>Probabilité</th><th>Impact</th><th>Atténuation</th></tr></thead>
    <tbody>
    @foreach($tc['hypotheses'] as $h)
        <tr>
            <td>{{ $h['code'] }}</td>
            <td>{{ \Illuminate\Support\Str::limit($h['libelle'], 60) }}</td>
            <td>{{ ucfirst((string) $h['probabilite']) }}</td>
            <td>{{ ucfirst((string) $h['impact']) }}</td>
            <td>{{ \Illuminate\Support\Str::limit($h['mitigation'] ?? '—', 50) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@else
<p class="vide">Aucun risque/hypothèse enregistré : l’impact suppose la tenue des conditions sectorielles habituelles.</p>
@endif
</body>
</html>
