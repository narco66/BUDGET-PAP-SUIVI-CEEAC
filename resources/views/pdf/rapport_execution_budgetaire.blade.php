@extends('pdf._layout')
@section('titre_doc', "RAPPORT D'EXÉCUTION BUDGÉTAIRE")
@section('numero', 'EXEC-'.$exercice->annee)
@section('exercice', $exercice->annee)
@php $fmt = fn ($n) => number_format((float)$n, 0, ',', ' '); @endphp

@section('corps')
<table class="infos">
    <tr><td class="k">Crédits ouverts</td><td class="montant">{{ $fmt($budget['ouvert']) }} XAF</td>
        <td class="k">Engagé</td><td class="montant">{{ $fmt($budget['engage']) }} ({{ $budget['taux_engagement'] }}%)</td></tr>
    <tr><td class="k">Liquidé</td><td class="montant">{{ $fmt($budget['liquide']) }}</td>
        <td class="k">Ordonnancé</td><td class="montant">{{ $fmt($budget['ordonnance']) }}</td></tr>
    <tr><td class="k">Payé</td><td class="montant">{{ $fmt($budget['paye']) }} ({{ $budget['taux_execution'] }}%)</td>
        <td class="k">Disponible</td><td class="montant">{{ $fmt($budget['disponible']) }}</td></tr>
</table>

<h4 style="color:#0a4a8f;font-size:13px;">Par nature (Titre)</h4>
<table class="lignes">
    <thead><tr><th>Titre</th><th>Intitulé</th><th>Ouvert</th><th>Engagé</th><th>Payé</th></tr></thead>
    <tbody>
    @foreach ($parNature as $n)
        <tr><td>{{ $n->code }}</td><td>{{ $n->intitule }}</td>
            <td class="montant">{{ $fmt($n->ouvert) }}</td><td class="montant">{{ $fmt($n->engage) }}</td><td class="montant">{{ $fmt($n->paye) }}</td></tr>
    @endforeach
    </tbody>
</table>

<h4 style="color:#0a4a8f;font-size:13px;margin-top:14px;">Par structure</h4>
<table class="lignes">
    <thead><tr><th>Structure</th><th>Ouvert</th><th>Engagé</th><th>Payé</th></tr></thead>
    <tbody>
    @foreach ($parStructure as $s)
        <tr><td>{{ $s->libelle }}</td><td class="montant">{{ $fmt($s->ouvert) }}</td><td class="montant">{{ $fmt($s->engage) }}</td><td class="montant">{{ $fmt($s->paye) }}</td></tr>
    @endforeach
    </tbody>
</table>
@endsection
