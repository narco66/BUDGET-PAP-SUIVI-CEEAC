@extends('layouts.app')
@section('titre', 'Authentification à deux facteurs')

@section('contenu')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-bold"><i class="bi bi-shield-lock me-1"></i>Authentification à deux facteurs (MFA)</div>
            <div class="card-body">
                @if (session('succes'))<div class="alert alert-success">{{ session('succes') }}</div>@endif
                @if (session('erreur'))<div class="alert alert-warning">{{ session('erreur') }}</div>@endif

                @if ($actif)
                    <p class="mb-3">La MFA est <span class="badge bg-success">active</span> sur votre compte depuis {{ auth()->user()->two_factor_confirmed_at?->format('d/m/Y') }}.</p>
                    @unless(app(\App\Services\Auth\MfaService::class)->estObligatoire(auth()->user()))
                        <form method="POST" action="{{ route('mfa.disable') }}" onsubmit="return confirm('Désactiver la MFA ?');">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Désactiver</button>
                        </form>
                    @else
                        <p class="text-muted small mb-0">Obligatoire pour votre profil institutionnel.</p>
                    @endunless
                @else
                    <p class="small text-muted">Scannez ce QR code avec Google Authenticator, Microsoft Authenticator ou une application TOTP compatible.</p>
                    <div class="text-center mb-3">
                        <img src="{{ $qr }}" alt="QR MFA" class="border rounded p-2 bg-white">
                        <p class="small mt-2 font-monospace">{{ $secret }}</p>
                    </div>
                    <form method="POST" action="{{ route('mfa.setup.confirm') }}">
                        @csrf
                        <x-form.input name="code" label="Code de vérification (6 chiffres)" maxlength="6" pattern="[0-9]{6}" required autofocus />
                        <x-form.actions :cancel-url="route('dashboard')" submit-label="Activer la MFA" />
                    </form>
                @endif

                @if ($codes = session('codes_secours'))
                    <div class="alert alert-warning mt-3">
                        <strong>Codes de secours — conservez-les en lieu sûr :</strong>
                        <ul class="mb-0 mt-2 font-monospace small">@foreach ($codes as $c)<li>{{ $c }}</li>@endforeach</ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
