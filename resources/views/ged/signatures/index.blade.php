@extends('layouts.app')
@section('titre', 'Signatures électroniques')
@section('contenu')
<h4 class="fw-bold mb-3">Registre des signatures</h4>
<div class="card border-0 shadow-sm table-responsive">
    <table class="table table-sm mb-0"><thead><tr><th>Document</th><th>Signataire</th><th>Statut</th><th>Date</th><th></th></tr></thead>
    <tbody>@foreach($signatures as $s)<tr>
        <td><a href="{{ route('ged.documents.show', $s->document) }}">{{ $s->document?->reference }}</a></td>
        <td>{{ $s->signataire?->name }}</td>
        <td><span class="badge bg-secondary">{{ $s->statut }}</span></td>
        <td>{{ $s->date_signature?->format('d/m/Y H:i') ?? '—' }}</td>
        <td>@if($s->statut === 'en_attente' && auth()->id() === $s->signataire_id)
            <form method="POST" action="{{ route('ged.signatures.signer', $s) }}" class="d-inline">@csrf<button class="btn btn-sm btn-success">Signer</button></form>
        @endif</td>
    </tr>@endforeach</tbody></table>
    <div class="card-footer">{{ $signatures->links() }}</div>
</div>
@endsection
