@extends('layouts.public')
@section('titre', 'Vérification GED · '.$document->reference)
@section('sous-titre', "Vérification d'authenticité d'un document GED")

@section('contenu')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                @if($resultat['integrite'])
                    <i class="bi bi-patch-check-fill fs-1 text-success"></i>
                    <h5 class="mt-2 text-success">Document intègre</h5>
                @else
                    <i class="bi bi-x-octagon fs-1 text-danger"></i>
                    <h5 class="mt-2 text-danger">Intégrité compromise</h5>
                @endif
            </div>

            <table class="table table-sm mb-0">
                <tr><th class="text-muted" style="width:40%">Référence</th><td class="font-monospace">{{ $document->reference }}</td></tr>
                <tr><th class="text-muted">Titre</th><td>{{ $document->titre }}</td></tr>
                <tr><th class="text-muted">Intégrité SHA-256</th><td>@if($resultat['integrite'])<span class="text-success">Document intact</span>@else<span class="text-danger">Intégrité compromise</span>@endif</td></tr>
                <tr><th class="text-muted">Signé</th><td>{{ $document->est_signe ? 'Oui' : 'Non' }}</td></tr>
                @if(!empty($resultat['niveau']))
                <tr><th class="text-muted">Niveau signature</th><td>{{ \App\Enums\Ged\SignatureNiveau::tryFrom($resultat['niveau'])?->libelle() ?? $resultat['niveau'] }}</td></tr>
                @endif
                @if(!empty($resultat['qualification_eidas']))
                <tr><th class="text-muted">Qualification eIDAS</th><td><span class="text-success">Signature qualifiée</span></td></tr>
                @endif
            </table>

            @if($resultat['signatures']->isNotEmpty())
                <hr><h6 class="brand">Signatures enregistrées</h6>
                <ul class="mb-0">
                    @foreach($resultat['signatures'] as $sig)
                        <li>{{ $sig->signataire?->name }} — {{ $sig->date_signature?->format('d/m/Y H:i') }}
                            @if($sig->niveau === 'qualifiee') <span class="badge bg-success">Qualifiée</span>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
