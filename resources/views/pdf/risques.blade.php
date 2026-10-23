@extends('pdf._layout')
@section('titre_doc', 'REGISTRE DES RISQUES')
@section('exercice', now()->year)

@section('corps')
<p style="font-size:11px">Criticité = Probabilité × Impact · {{ $risques->count() }} risque(s) · généré le {{ now()->format('d/m/Y H:i') }}</p>
<table style="width:100%; border-collapse:collapse; font-size:9px">
    <thead>
        <tr style="background:#0a4a8f; color:#fff">
            <th style="border:1px solid #999; padding:3px">Code</th>
            <th style="border:1px solid #999; padding:3px">Catégorie</th>
            <th style="border:1px solid #999; padding:3px">Libellé</th>
            <th style="border:1px solid #999; padding:3px">P</th>
            <th style="border:1px solid #999; padding:3px">I</th>
            <th style="border:1px solid #999; padding:3px">Crit.</th>
            <th style="border:1px solid #999; padding:3px">Niveau</th>
            <th style="border:1px solid #999; padding:3px">Statut</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($risques as $r)
        <tr>
            <td style="border:1px solid #ccc; padding:3px">{{ $r->code }}</td>
            <td style="border:1px solid #ccc; padding:3px">{{ $r->categorie?->libelle ?? '—' }}</td>
            <td style="border:1px solid #ccc; padding:3px">{{ $r->libelle }}</td>
            <td style="border:1px solid #ccc; padding:3px; text-align:center">{{ $r->probabilite }}</td>
            <td style="border:1px solid #ccc; padding:3px; text-align:center">{{ $r->impact }}</td>
            <td style="border:1px solid #ccc; padding:3px; text-align:center"><strong>{{ $r->criticite }}</strong></td>
            <td style="border:1px solid #ccc; padding:3px">{{ \App\Models\Risque::niveauMeta($r->niveau)['libelle'] }}</td>
            <td style="border:1px solid #ccc; padding:3px">{{ \App\Models\Risque::STATUTS[$r->statut] ?? $r->statut }}</td>
        </tr>
    @empty
        <tr><td colspan="8" style="border:1px solid #ccc; padding:6px; text-align:center">Aucun risque enregistré.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
