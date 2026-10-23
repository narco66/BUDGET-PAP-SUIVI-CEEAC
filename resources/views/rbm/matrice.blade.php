@extends('layouts.app')
@section('titre', 'Matrice RBM · '.$exercice->annee)

@section('contenu')
@include('rbm._nav-module', ['exercice' => $exercice, 'page' => 'matrice'])

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Pilier</th><th>Axe</th><th>Produit</th><th>Sous-produit</th><th>Activité</th>
                    <th>Intitulé</th><th class="text-end">Montant</th><th class="text-center">Tâches</th><th class="text-center">IOV</th>
                </tr>
            </thead>
            <tbody>
            @forelse($lignes as $l)
                <tr>
                    <td><code>{{ $l['pilier'] }}</code></td>
                    <td><code>{{ $l['axe'] }}</code></td>
                    <td><code>{{ $l['produit'] }}</code></td>
                    <td><code>{{ $l['sous_produit'] }}</code></td>
                    <td><code>{{ $l['activite'] }}</code></td>
                    <td>{{ \Illuminate\Support\Str::limit($l['intitule'], 60) }}</td>
                    <td class="text-end">{{ number_format($l['montant'], 0, ',', ' ') }}</td>
                    <td class="text-center">{{ $l['nb_taches'] }}</td>
                    <td class="text-center">{{ $l['nb_indicateurs'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Aucune donnée pour cet exercice.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
