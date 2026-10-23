@php
    use App\Services\Reporting\Fmt;
    $charte = $charte ?? \App\Support\CharteCeeac::export();
    $f = fn ($v, $fmt) => Fmt::valeur($v, $fmt);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
@include('reporting.pdf._charte_styles')
</head>
<body>
    <div class="entete">
        <span class="inst">{{ $charte['institution'] }}</span>
        <div class="sub">{{ $charte['sous_titre'] }}</div>
        <div class="sys">{{ $charte['systeme'] }}</div>
    </div>

    <h1 class="titre">{{ $c['titre'] }}</h1>
    <div class="soustitre">{{ $c['sous_titre'] }}</div>
    <div class="ref">Réf. {{ $meta['reference'] }} · version {{ $meta['version'] }} · généré le {{ $meta['date'] }} par {{ $meta['auteur'] }}</div>

    @foreach ($c['sections'] as $i => $s)
        <h2 class="section">{{ $i + 1 }}. {{ $s['titre'] ?? '' }}</h2>

        @if ($s['type'] === 'kpi')
            <div class="kpis">
                @foreach ($s['items'] as $it)
                    <div class="kpi"><div class="lbl">{{ $it['label'] }}</div><div class="val">{{ $f($it['valeur'], $it['fmt'] ?? 'text') }}</div></div>
                @endforeach
            </div>
        @elseif ($s['type'] === 'table')
            <table>
                <thead><tr>
                    @foreach ($s['colonnes'] as $col)
                        <th class="{{ ($col['align'] ?? 'left') === 'right' ? 'r' : '' }}">{{ $col['label'] }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @foreach ($s['lignes'] as $ri => $ligne)
                        <tr class="{{ isset($s['niveaux']) ? 'niv'.$s['niveaux'][$ri] : '' }}">
                            @foreach ($ligne as $ci => $val)
                                <td class="{{ ($s['colonnes'][$ci]['align'] ?? 'left') === 'right' ? 'r' : '' }}">{{ $f($val, $s['colonnes'][$ci]['fmt'] ?? 'text') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    @if (! empty($s['total']))
                        <tr class="total">
                            @foreach ($s['total'] as $ci => $val)
                                <td class="{{ ($s['colonnes'][$ci]['align'] ?? 'left') === 'right' ? 'r' : '' }}">{{ $f($val, $s['colonnes'][$ci]['fmt'] ?? 'text') }}</td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
        @elseif ($s['type'] === 'liste')
            <ul>@foreach ($s['items'] as $it)<li>{{ $it }}</li>@endforeach</ul>
        @else
            <p class="txt">{{ $s['contenu'] ?? '' }}</p>
        @endif
    @endforeach

    <table class="pied"><tr>
        <td style="border:none; width:75%; vertical-align:top;">
            <strong>Authenticité du document</strong><br>
            @if ($meta['signataire'])
                Signé électroniquement par {{ $meta['signataire'] }} le {{ $meta['signe_le'] }}.<br>
            @else
                Document non signé électroniquement.<br>
            @endif
            <span class="lien">Vérifiez ce rapport : {{ $meta['verification'] }}</span>
            <div class="mention">{{ $charte['mention_pied'] }} · {{ $charte['confidentialite'] }}</div>
        </td>
        <td style="border:none; width:25%; text-align:right;">
            @if (! empty($meta['qr']))<img src="{{ $meta['qr'] }}" width="95" height="95">@endif
        </td>
    </tr></table>
</body>
</html>
