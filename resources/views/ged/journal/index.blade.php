@extends('layouts.app')
@section('titre', 'Journal documentaire')

@section('contenu')
@include('ged._nav-module', ['page' => 'journal'])

<h4 class="fw-bold mb-3">Journal documentaire</h4>
<div class="card border-0 shadow-sm table-responsive">
    <table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Action</th><th>Document</th><th>Utilisateur</th><th>IP</th></tr></thead>
    <tbody>@foreach($journaux as $j)<tr>
        <td class="small">{{ $j->created_at->format('d/m/Y H:i:s') }}</td>
        <td><span class="badge bg-light text-dark">{{ $j->action }}</span></td>
        <td>@if($j->document)<a href="{{ route('ged.documents.show', $j->document) }}">{{ Str::limit($j->document->reference, 25) }}</a>@else—@endif</td>
        <td>{{ $j->utilisateur?->name ?? '—' }}</td>
        <td class="small">{{ $j->adresse_ip }}</td>
    </tr>@endforeach</tbody></table>
    <div class="card-footer">{{ $journaux->links() }}</div>
</div>
@endsection
