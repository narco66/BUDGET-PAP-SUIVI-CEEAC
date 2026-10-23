@extends('pdf._layout')
@section('titre_doc', 'FICHE D\'EXPRESSION DU BESOIN')
@section('numero', $besoin->numero)
@section('exercice', $besoin->exercice?->annee)

@section('corps')
<table class="infos">
    <tr><td class="k">Objet</td><td>{{ $besoin->objet }}</td></tr>
    <tr><td class="k">Structure demandeuse</td><td>{{ $besoin->structure?->libelle ?? '—' }}</td></tr>
    <tr><td class="k">Demandeur</td><td>{{ $besoin->demandeur?->name ?? '—' }}</td></tr>
    <tr><td class="k">Activité rattachée</td><td>{{ $besoin->activite?->code ?? '—' }} {{ $besoin->activite?->intitule }}</td></tr>
    <tr><td class="k">Montant estimé</td><td>{{ number_format((float)$besoin->montant_estime, 0, ',', ' ') }} XAF</td></tr>
    <tr><td class="k">Date du besoin</td><td>{{ $besoin->date_besoin?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><td class="k">Statut</td><td>{{ strtoupper(str_replace('_',' ', $besoin->statut)) }}</td></tr>
</table>

<p><strong>Justification :</strong></p>
<p>{{ $besoin->justification ?? '—' }}</p>

<table class="signatures">
    <tr>
        <td>Le demandeur<br>{{ $besoin->demandeur?->name }}</td>
        <td>Le chef de service</td>
        <td>Le directeur</td>
    </tr>
</table>
@endsection
