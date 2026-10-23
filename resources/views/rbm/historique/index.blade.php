@extends('layouts.app')
@section('titre', 'Historique RBM · '.$exercice->annee)

@section('contenu')
@include('rbm._nav-module', ['exercice' => $exercice, 'page' => 'historique'])

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0"><i class="bi bi-clock-history me-2"></i>Journal des modifications RBM</h1>
    <form method="GET" class="d-flex gap-2">
        <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Toutes actions</option>
            @foreach(['create','update','delete','archive','restore'] as $a)
                <option value="{{ $a }}" @selected(request('action')===$a)>{{ $a }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr><th>Date</th><th>Action</th><th>Entité</th><th>Utilisateur</th><th>Détails</th></tr>
            </thead>
            <tbody>
            @forelse($historiques as $h)
                <tr>
                    <td class="small text-muted">{{ $h->created_at->format('d/m/Y H:i') }}</td>
                    <td><span class="badge bg-secondary">{{ $h->action }}</span></td>
                    <td class="small"><code>{{ class_basename($h->historisable_type) }}</code> #{{ $h->historisable_id }}</td>
                    <td class="small">{{ $h->utilisateur?->name ?? '—' }}</td>
                    <td class="small text-muted">
                        @if($h->donnees_apres)
                            @if(isset($h->donnees_apres['code']))<code>{{ $h->donnees_apres['code'] }}</code>@endif
                            {{ $h->donnees_apres['intitule'] ?? $h->donnees_apres['libelle'] ?? '' }}
                        @elseif($h->donnees_avant)
                            Suppression : <code>{{ $h->donnees_avant['code'] ?? '' }}</code>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Aucune modification enregistrée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($historiques->hasPages())<div class="card-footer">{{ $historiques->links() }}</div>@endif
</div>
@endsection
