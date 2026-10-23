@extends('layouts.app')
@section('titre', 'Rapprochement bancaire')
@section('contenu')
<div class="d-flex justify-content-between mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-bank me-1"></i>Sessions de rapprochement</h5>
    <a href="{{ route('depense.rapprochement.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvelle session</a>
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Réf.</th><th>Intitulé</th><th>Exercice</th><th class="text-end">Relevé</th><th class="text-end">Rapproché</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse($sessions as $s)
            <tr>
                <td><code>{{ $s->reference }}</code></td>
                <td>{{ $s->intitule }}</td>
                <td>{{ $s->exercice?->annee }}</td>
                <td class="text-end">{{ number_format($s->montant_releve,0,',',' ') }}</td>
                <td class="text-end">{{ number_format($s->montant_rapproche,0,',',' ') }}</td>
                <td><span class="badge bg-secondary">{{ str_replace('_',' ',$s->statut) }}</span></td>
                <td class="text-end"><a href="{{ route('depense.rapprochement.show', $s) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Aucune session.</td></tr>
        @endforelse
        </tbody>
    </table>
</div><div class="card-footer">{{ $sessions->links() }}</div></div>
@endsection
