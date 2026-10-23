<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>PAP {{ $pap->exercice?->annee }}</title>
<style>
    @page { margin: 22px 26px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }
    h1 { font-size: 15px; color: #0a4a8f; margin: 0 0 2px; }
    h2 { font-size: 10px; color: #0a4a8f; margin: 14px 0 4px; border-bottom: 1px solid #0a4a8f; padding-bottom: 2px; }
    .ref { color: #555; font-size: 9px; }
    .meta { color: #777; font-size: 8px; margin-bottom: 6px; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; color: #fff; font-size: 8px; }
    .b-brouillon { background: #6c757d; } .b-en_validation { background: #cc9a06; }
    .b-valide { background: #0aa2c0; } .b-publie { background: #198754; }
    .b-rejete { background: #dc3545; } .b-clos { background: #343a40; } .b-en_revision { background: #0d6efd; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th, td { border: 1px solid #ddd; padding: 2px 4px; vertical-align: top; }
    th { background: #0a4a8f; color: #fff; text-align: left; font-weight: normal; }
    td.num, th.num { text-align: right; }
    td.ctr, th.ctr { text-align: center; }
    .infos td { border: none; padding: 1px 4px; }
    .infos td.lbl { color: #777; width: 90px; }
    .kpi { width: 100%; margin: 4px 0; }
    .kpi td { border: 1px solid #cfe2f3; text-align: center; padding: 6px; }
    .kpi .big { font-size: 16px; font-weight: bold; color: #0a4a8f; }
    .kpi .lbl { font-size: 7.5px; color: #777; }
    .warn { background: #fff3cd; }
    tr { page-break-inside: avoid; }
    .empty { text-align: center; color: #999; padding: 6px; }
</style></head>
<body>
@php $fr = fn ($n) => number_format((float) $n, 0, ',', ' '); @endphp

<h1>Plan d'Action Prioritaire — Exercice {{ $pap->exercice?->annee }}</h1>
<div class="ref">{{ $pap->libelle }} @if($pap->reference)· {{ $pap->reference }}@endif</div>
<div class="meta">Généré le {{ now()->format('d/m/Y à H:i') }}</div>

<table class="infos">
    <tr>
        <td class="lbl">Structure</td><td>{{ $pap->structure?->libelle ?? '—' }}</td>
        <td class="lbl">Statut</td><td><span class="badge b-{{ $pap->statut }}">{{ str_replace('_', ' ', $pap->statut) }}</span> · v{{ $pap->version }}</td>
    </tr>
    <tr>
        <td class="lbl">Responsable</td><td>{{ $pap->responsable?->name ?? '—' }}</td>
        <td class="lbl">Élaboré le</td><td>{{ $pap->date_elaboration?->format('d/m/Y') ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Créé par</td><td>{{ $pap->createur?->name ?? '—' }}</td>
        <td class="lbl">Publié le</td><td>{{ $pap->publie_le?->format('d/m/Y H:i') ?? '—' }}</td>
    </tr>
    @if($pap->validated_at)
    <tr>
        <td class="lbl">Validé le</td><td>{{ $pap->validated_at->format('d/m/Y H:i') }} @if($pap->validateur)· {{ $pap->validateur->name }}@endif</td>
        <td class="lbl"></td><td></td>
    </tr>
    @endif
    @if($pap->observations)
    <tr><td class="lbl">Observations</td><td colspan="3">{{ $pap->observations }}</td></tr>
    @endif
</table>

<table class="kpi">
    <tr>
        <td><div class="big">{{ $synthese['activites'] }}</div><div class="lbl">Activités</div></td>
        <td><div class="big">{{ $synthese['taux_physique'] }} %</div><div class="lbl">Taux physique</div></td>
        <td><div class="big">{{ $synthese['taux_financier'] }} %</div><div class="lbl">Taux financier réel</div></td>
    </tr>
</table>

<h2>Performance par produit ({{ $performanceProduits->count() }})</h2>
<table>
    <thead><tr>
        <th>Code</th><th>Produit</th><th class="ctr">Act.</th>
        <th class="num">Budget</th><th class="num">Payé</th>
        <th class="num">Phys.</th><th class="num">Fin.</th><th class="num">Indice</th>
    </tr></thead>
    <tbody>
    @forelse($performanceProduits as $p)
        <tr>
            <td>{{ $p['code'] }}</td>
            <td>{{ Str::limit($p['intitule'], 55) }}</td>
            <td class="ctr">{{ $p['activites'] }}</td>
            <td class="num">{{ $fr($p['budget_prevu']) }}</td>
            <td class="num">{{ $fr($p['budget_paye']) }}</td>
            <td class="num">{{ $p['taux_physique'] }} %</td>
            <td class="num">{{ $p['taux_financier'] }} %</td>
            <td class="num">{{ $p['taux_physique'] > 0 || $p['taux_financier'] > 0 ? round(($p['taux_physique'] + $p['taux_financier']) / 2, 1) : 0 }} %</td>
        </tr>
    @empty
        <tr><td colspan="8" class="empty">Aucun produit.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Performance par activité ({{ $performanceActivites->count() }})</h2>
<table>
    <thead><tr>
        <th>Code</th><th>Activité</th>
        <th class="num">Phys.</th><th class="num">Fin.</th>
        <th class="num">Payé</th><th class="num">Indice</th><th class="ctr">Alerte</th>
    </tr></thead>
    <tbody>
    @forelse($performanceActivites as $a)
        <tr class="{{ ($a['en_retard'] || $a['surconsommation']) ? 'warn' : '' }}">
            <td>{{ $a['code'] }}</td>
            <td>{{ Str::limit($a['intitule'], 60) }}</td>
            <td class="num">{{ $a['taux_physique'] }} %</td>
            <td class="num">{{ $a['taux_financier'] }} %</td>
            <td class="num">{{ $fr($a['montant_paye']) }}</td>
            <td class="num">{{ $a['indice_performance'] }} %</td>
            <td class="ctr">
                @if($a['surconsommation'])Surconso.@elseif($a['en_retard'])Retard@else—@endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="empty">Aucune activité.</td></tr>
    @endforelse
    </tbody>
</table>

@if($classementDepartements->isNotEmpty())
<h2>Classement des départements</h2>
<table>
    <thead><tr><th class="ctr">#</th><th>Département</th><th class="num">Indice</th><th class="num">Phys.</th><th class="num">Fin.</th></tr></thead>
    <tbody>
    @foreach($classementDepartements as $d)
        <tr>
            <td class="ctr">{{ $d['rang'] }}</td>
            <td>{{ $d['libelle'] }}</td>
            <td class="num">{{ $d['indice_performance'] }} %</td>
            <td class="num">{{ $d['taux_physique'] }} %</td>
            <td class="num">{{ $d['taux_financier'] }} %</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@if($consolidation->isNotEmpty())
<h2>Consolidation par structure</h2>
<table>
    <thead><tr><th>Structure</th><th class="ctr">Act.</th><th class="num">Phys.</th><th class="num">Fin.</th></tr></thead>
    <tbody>
    @foreach($consolidation as $s)
        <tr>
            <td>{{ $s['libelle'] }}</td>
            <td class="ctr">{{ $s['activites'] }}</td>
            <td class="num">{{ $s['taux_physique'] }} %</td>
            <td class="num">{{ $s['taux_financier'] }} %</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

@if($risques->isNotEmpty())
<h2>Risques (GAR) — {{ $risques->count() }}</h2>
<table>
    <thead><tr><th>Code</th><th>Risque</th><th>Activité</th><th>Responsable</th></tr></thead>
    <tbody>
    @foreach($risques as $r)
        <tr>
            <td>{{ $r->code }}</td>
            <td>{{ Str::limit($r->libelle ?? '—', 70) }}</td>
            <td>{{ $r->activite?->code ?? '—' }}</td>
            <td>{{ $r->responsable?->name ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_script('
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $txt = "CEEAC · PAP " . "{{ $pap->exercice?->annee }}" . " — page " . $PAGE_NUM . " / " . $PAGE_COUNT;
            $pdf->text(26, 812, $txt, $font, 7, array(0.6,0.6,0.6));
        ');
    }
</script>
</body>
</html>
