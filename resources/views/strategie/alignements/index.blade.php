@extends('layouts.app')
@section('titre', 'Alignements stratégiques')
@section('contenu')
@include('strategie._nav', ['active' => 'alignements'])
<div class="d-flex justify-content-end mb-3">@can('strategie.create')<a href="{{ route('strategie.alignements.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouvel alignement</a>@endcan</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0">
<thead class="table-light"><tr><th>Vision</th><th>Orientation</th><th>Entité</th><th>Niveau</th><th>Commentaire</th><th class="text-end">Actions</th></tr></thead>
<tbody>@forelse($alignements as $a)<tr>
<td>{{ $a->vision?->reference ?? '—' }}</td><td>{{ $a->orientationStrategique?->code ?? '—' }}</td>
<td class="small">{{ class_basename($a->alignable_type) }} #{{ $a->alignable_id }} @if($a->alignable) — {{ Str::limit($a->alignable->intitule ?? $a->alignable->code ?? '', 30) }}@endif</td>
<td><span class="badge bg-secondary">{{ $a->niveau_alignement }}</span></td><td class="small">{{ Str::limit($a->commentaire, 40) }}</td>
<td class="text-end">@can('strategie.delete')<form method="POST" action="{{ route('strategie.alignements.destroy', $a) }}" class="d-inline" onsubmit="return confirm('Supprimer ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>@endcan</td>
</tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">Aucun alignement.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $alignements->links() }}</div>
@endsection
