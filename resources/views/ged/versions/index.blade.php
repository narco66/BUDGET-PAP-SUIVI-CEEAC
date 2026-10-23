@extends('layouts.app')
@section('titre', 'Versions de documents')
@section('contenu')
<h4 class="fw-bold mb-3">Historique des versions</h4>
<div class="card border-0 shadow-sm table-responsive">
    <table class="table table-sm mb-0"><thead><tr><th>Document</th><th>v</th><th>Fichier</th><th>Hash</th><th>Auteur</th><th>Date</th></tr></thead>
    <tbody>@foreach($versions as $v)<tr>
        <td><a href="{{ route('ged.documents.show', $v->document) }}">{{ $v->document?->reference }}</a></td>
        <td>{{ $v->numero }}</td><td>{{ $v->nom_original }}</td>
        <td class="small"><code>{{ Str::limit($v->hash_sha256, 12) }}</code></td>
        <td>{{ $v->auteur?->name }}</td><td>{{ $v->created_at->format('d/m/Y') }}</td>
    </tr>@endforeach</tbody></table>
    <div class="card-footer">{{ $versions->links() }}</div>
</div>
@endsection
