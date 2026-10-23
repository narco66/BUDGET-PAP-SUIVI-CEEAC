@extends('pdf._layout')
@section('titre_doc', $config['pdf_titre'])
@section('numero', $etape->numero)
@section('exercice', $engagement->exercice?->annee)

@php $fmt = fn ($n) => number_format((float)$n, 0, ',', ' '); @endphp

@section('corps')
<table class="infos">
    <tr><td class="k">Engagement d'origine</td><td>{{ $engagement->numero }} — {{ $engagement->objet }}</td></tr>
    <tr><td class="k">Montant</td><td>{{ $fmt($etape->montant) }} XAF</td></tr>
    <tr><td class="k">Date</td><td>{{ $etape->{$config['date_cle']}?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><td class="k">Statut</td><td>{{ strtoupper(str_replace('_',' ', $etape->statut)) }}</td></tr>
    @if($etape->service_fait)<tr><td class="k">Service fait</td><td>{{ $etape->service_fait }}</td></tr>@endif
    @if($etape->mode_paiement ?? null)<tr><td class="k">Mode de paiement</td><td>{{ $etape->mode_paiement }} {{ $etape->reference_bancaire }}</td></tr>@endif
    <tr><td class="k">Établi par</td><td>{{ $etape->createur?->name ?? '—' }}</td></tr>
</table>

<table class="lignes">
    <thead><tr><th>Ligne budgétaire</th><th>Rubrique</th><th>Montant (XAF)</th></tr></thead>
    <tbody>
    @foreach ($engagement->imputations as $imp)
        <tr>
            <td>{{ $imp->ligneBudgetaire?->code }}</td>
            <td>{{ $imp->ligneBudgetaire?->rubrique?->code }}</td>
            <td class="montant">{{ $fmt($imp->montant) }}</td>
        </tr>
    @endforeach
        <tr class="total-row"><td colspan="2">TOTAL</td><td class="montant">{{ $fmt($etape->montant) }}</td></tr>
    </tbody>
</table>

<table class="signatures">
    <tr>
        <td>L'ordonnateur</td>
        <td>Le contrôleur financier</td>
        <td>Le comptable</td>
    </tr>
</table>
@endsection
