@extends('pdf._layout')
@section('titre_doc', 'FICHE D\'ENGAGEMENT')
@section('numero', $engagement->numero)
@section('exercice', $engagement->exercice?->annee)

@php $fmt = fn ($n) => number_format((float)$n, 0, ',', ' '); @endphp

@section('corps')
<table class="infos">
    <tr><td class="k">Objet</td><td>{{ $engagement->objet }}</td></tr>
    <tr><td class="k">Type d'engagement</td><td>{{ str_replace('_',' ', $engagement->type) }}</td></tr>
    <tr><td class="k">Bénéficiaire</td><td>{{ $engagement->beneficiaire ?? '—' }}</td></tr>
    <tr><td class="k">Date</td><td>{{ $engagement->date_engagement?->format('d/m/Y') ?? '—' }}</td></tr>
    <tr><td class="k">Statut</td><td>{{ strtoupper(str_replace('_',' ', $engagement->statut)) }}</td></tr>
    <tr><td class="k">Établi par</td><td>{{ $engagement->createur?->name ?? '—' }}</td></tr>
    <tr><td class="k">Visa contrôleur financier</td><td>{{ $engagement->controleur?->name ?? 'En attente' }} {{ $engagement->vise_le ? '· '.$engagement->vise_le->format('d/m/Y H:i') : '' }}</td></tr>
</table>

<table class="lignes">
    <thead><tr><th>Ligne budgétaire</th><th>Rubrique</th><th>Structure</th><th>Montant imputé (XAF)</th></tr></thead>
    <tbody>
    @foreach ($engagement->imputations as $imp)
        <tr>
            <td>{{ $imp->ligneBudgetaire?->code }}</td>
            <td>{{ $imp->ligneBudgetaire?->rubrique?->code }}</td>
            <td>{{ $imp->ligneBudgetaire?->structure?->sigle ?? '—' }}</td>
            <td class="montant">{{ $fmt($imp->montant) }}</td>
        </tr>
    @endforeach
        <tr class="total-row"><td colspan="3">TOTAL ENGAGÉ</td><td class="montant">{{ $fmt($engagement->montant_total) }}</td></tr>
    </tbody>
</table>

<table class="signatures">
    <tr>
        <td>L'ordonnateur<br>{{ $engagement->createur?->name }}</td>
        <td>Le contrôleur financier<br>{{ $engagement->controleur?->name ?? '' }}</td>
        <td>Le comptable</td>
    </tr>
</table>
@endsection
