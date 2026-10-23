@extends('pdf._layout')
@section('titre_doc', 'CERTIFICAT DE RÉSERVATION DE CRÉDITS')
@section('numero', $engagement->numero)
@section('exercice', $engagement->exercice?->annee)

@php $fmt = fn ($n) => number_format((float)$n, 0, ',', ' '); @endphp

@section('corps')
<p>Le présent certificat atteste la réservation des crédits ci-dessous au titre de l'engagement
<strong>{{ $engagement->numero }}</strong> — « {{ $engagement->objet }} ».</p>

<table class="lignes">
    <thead><tr><th>Ligne</th><th>Ouvert</th><th>Engagé (après réservation)</th><th>Disponible</th><th>Réservé</th></tr></thead>
    <tbody>
    @foreach ($engagement->imputations as $imp)
        @php $l = $imp->ligneBudgetaire; @endphp
        <tr>
            <td>{{ $l?->code }}</td>
            <td class="montant">{{ $fmt($l?->montant_ouvert) }}</td>
            <td class="montant">{{ $fmt($l?->montant_engage) }}</td>
            <td class="montant">{{ $fmt($l?->disponible) }}</td>
            <td class="montant">{{ $fmt($imp->montant) }}</td>
        </tr>
    @endforeach
        <tr class="total-row"><td colspan="4">TOTAL RÉSERVÉ</td><td class="montant">{{ $fmt($engagement->montant_total) }}</td></tr>
    </tbody>
</table>

<p style="margin-top:14px;">État de la réservation :
    <span class="badge" style="background:{{ $engagement->credits_reserves ? '#198754' : '#6c757d' }}; color:#fff;">
        {{ $engagement->credits_reserves ? 'CRÉDITS RÉSERVÉS' : 'NON RÉSERVÉ' }}
    </span>
</p>

<table class="signatures">
    <tr>
        <td>Le contrôleur financier<br>{{ $engagement->controleur?->name ?? '' }}</td>
        <td>L'ordonnateur<br>{{ $engagement->createur?->name }}</td>
    </tr>
</table>
@endsection
