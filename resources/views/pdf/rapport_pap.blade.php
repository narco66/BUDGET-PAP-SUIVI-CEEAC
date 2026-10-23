@extends('pdf._layout')
@section('titre_doc', 'RAPPORT DU PLAN ANNUEL DE PERFORMANCE')
@section('numero', 'PAP-'.$exercice->annee)
@section('exercice', $exercice->annee)
@php $fmt = fn ($n) => number_format((float)$n, 0, ',', ' '); @endphp

@section('corps')
<table class="infos">
    <tr><td class="k">Activités planifiées</td><td>{{ $pap['activites'] }}</td></tr>
    <tr><td class="k">Taux de réalisation physique moyen</td><td>{{ $pap['taux_physique'] }}%</td></tr>
    <tr><td class="k">Taux de réalisation financier moyen</td><td>{{ $pap['taux_financier'] }}%</td></tr>
</table>

<h4 style="color:#0a4a8f;font-size:13px;">Synthèse par pilier</h4>
<table class="lignes">
    <thead><tr><th>Pilier</th><th>Intitulé</th><th>Activités</th><th>Montant total</th><th>CEEAC</th><th>PTF</th></tr></thead>
    <tbody>
    @forelse ($parPilier as $p)
        <tr>
            <td>{{ $p->code }}</td><td>{{ $p->intitule }}</td><td>{{ $p->activites }}</td>
            <td class="montant">{{ $fmt($p->montant) }}</td><td class="montant">{{ $fmt($p->ceeac) }}</td><td class="montant">{{ $fmt($p->ptf) }}</td>
        </tr>
    @empty
        <tr><td colspan="6">Aucun pilier défini pour cet exercice.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
