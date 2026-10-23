@extends('layouts.app')
@section('titre', 'Historique · '.$config['label'])

@section('contenu')
<div class="mb-3">
    <span class="badge bg-secondary">{{ strtoupper($config['label']) }}</span>
    <code>{{ $noeud->code }}</code> {{ $noeud->intitule }}
    <a href="{{ route('rbm.arbre', request('exercice', 1)) }}" class="btn btn-sm btn-light float-end">Retour</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Action</th><th>Utilisateur</th><th>Changements</th></tr></thead>
            <tbody>
            @forelse($historiques as $h)
                <tr>
                    <td class="small">{{ $h->created_at->format('d/m/Y H:i') }}</td>
                    <td><span class="badge bg-info">{{ $h->action }}</span></td>
                    <td class="small">{{ $h->utilisateur?->name ?? '—' }}</td>
                    <td class="small">
                        @foreach(($h->donnees_apres ?? []) as $k => $v)
                            @if(is_scalar($v) && isset($h->donnees_avant[$k]) && $h->donnees_avant[$k] != $v)
                                <div><strong>{{ $k }}</strong> : {{ $h->donnees_avant[$k] }} → {{ $v }}</div>
                            @endif
                        @endforeach
                        @if($h->action==='create')Création de l'élément.@endif
                        @if($h->action==='delete')Suppression.@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-3">Aucun historique.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($historiques->hasPages())<div class="card-footer">{{ $historiques->links() }}</div>@endif
</div>
@endsection
