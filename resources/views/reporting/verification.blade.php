@extends('layouts.public')
@section('titre', 'Vérification de rapport')
@section('sous-titre', "Vérification d'authenticité d'un rapport institutionnel")

@section('contenu')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            @if (! $authentique)
                <div class="text-center text-danger">
                    <i class="bi bi-x-octagon fs-1"></i>
                    <h5 class="mt-2">Rapport introuvable</h5>
                    <p class="text-muted">Aucun rapport ne correspond à ce code de vérification. Ce document pourrait ne pas être authentique.</p>
                </div>
            @else
                <div class="text-center mb-3">
                    @if ($signatureValide)
                        <i class="bi bi-patch-check-fill fs-1 text-success"></i>
                        <h5 class="mt-2 text-success">Document authentique et signé</h5>
                        <p class="text-muted mb-0">La signature électronique est valide et vérifiée.</p>
                    @else
                        <i class="bi bi-shield-check fs-1 brand"></i>
                        <h5 class="mt-2 brand">Rapport authentique</h5>
                        <p class="text-muted mb-0">Ce rapport a bien été émis par le système. Il n'a pas (encore) été signé électroniquement.</p>
                    @endif
                </div>

                <table class="table table-sm">
                    <tr><th class="text-muted" style="width:40%">Référence</th><td class="font-monospace">{{ $rapport->reference }}</td></tr>
                    <tr><th class="text-muted">Titre</th><td>{{ $rapport->titre }}</td></tr>
                    <tr><th class="text-muted">Exercice</th><td>{{ $rapport->exercice?->annee ?? '—' }}</td></tr>
                    <tr><th class="text-muted">Format / Version</th><td class="text-uppercase">{{ $rapport->format }} · v{{ $rapport->version }}</td></tr>
                    <tr><th class="text-muted">Généré le</th><td>{{ $rapport->created_at->format('d/m/Y à H:i') }}</td></tr>
                    <tr><th class="text-muted">Généré par</th><td>{{ $rapport->generePar?->name ?? '—' }}</td></tr>
                    @if ($rapport->estSigne())
                        <tr><th class="text-muted">Signé par</th><td>{{ $rapport->signePar?->name ?? '—' }} le {{ $rapport->signe_le?->format('d/m/Y H:i') }}</td></tr>
                    @endif
                    <tr><th class="text-muted">Empreinte SHA-256</th><td class="font-monospace" style="font-size:.7rem;word-break:break-all">{{ $rapport->hash }}</td></tr>
                </table>
            @endif
        </div>
    </div>
@endsection
