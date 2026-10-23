@extends('layouts.app')
@section('titre', 'Corbeille validations')

@section('contenu')
<h4 class="fw-bold mb-3"><i class="bi bi-inbox me-2"></i>Validations en attente</h4>
<p class="text-muted small">Tous les circuits D14 où vous êtes habilité à décider sur l'étape courante.</p>

<div class="card border-0 shadow-sm">
    @forelse ($taches as $t)
        <div class="card-body border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="badge bg-light text-dark border me-1">{{ $t['circuit'] }}</span>
                @if ($t['en_retard'])<span class="badge bg-danger">En retard</span>@endif
                <div class="fw-semibold mt-1">{{ $t['libelle'] }}</div>
                <div class="small text-muted">
                    Étape {{ $t['etape']?->ordre }} · {{ str_replace('_', ' ', $t['etape']?->role ?? '') }}
                    @if ($t['etape']?->echeance_le)
                        · Échéance {{ $t['etape']->echeance_le->format('d/m/Y') }}
                    @endif
                </div>
            </div>
            @if ($t['url'])
            <a href="{{ $t['url'] }}" class="btn btn-sm btn-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Ouvrir</a>
            @endif
        </div>
    @empty
        <div class="card-body text-muted">Aucune validation en attente pour votre profil.</div>
    @endforelse
</div>
@endsection
