@extends('layouts.app')
@section('titre', $config['pluriel'])
@php $c = ['brouillon'=>'secondary','soumis'=>'warning','valide'=>'success','rejete'=>'danger']; @endphp
@section('contenu')
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>N°</th><th class="text-end">Montant</th><th>Date</th><th>Statut</th><th></th></tr></thead>
        <tbody>
        @forelse ($items as $it)
            <tr>
                <td><code>{{ $it->numero }}</code></td>
                <td class="text-end">{{ number_format($it->montant,0,',',' ') }}</td>
                <td class="small text-muted">{{ $it->{$config['date_cle']}?->format('d/m/Y') ?? '—' }}</td>
                <td><span class="badge bg-{{ $c[$it->statut] ?? 'secondary' }}">{{ str_replace('_',' ',$it->statut) }}</span></td>
                <td class="text-end"><a href="{{ route('depense.show', [$type, $it->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
            </tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-4">Aucune {{ strtolower($config['label']) }}. Créez-la depuis un(e) {{ strtolower($config['parent_label']) }} validé(e).</td></tr>@endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $items->links() }}</div>
@endsection
