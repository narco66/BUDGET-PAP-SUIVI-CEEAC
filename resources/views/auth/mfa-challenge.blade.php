@extends('layouts.guest')
@section('titre', 'Vérification MFA')
@section('sous-titre', 'Second facteur d\'authentification')

@section('contenu')
    <h6 class="fw-bold text-center mb-3"><i class="bi bi-shield-check me-1" style="color:var(--ceeac)"></i>Code d'authentification</h6>
    <p class="text-muted small text-center">Saisissez le code à 6 chiffres de votre application d'authentification, ou un code de secours.</p>

    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('mfa.challenge.verify') }}">
        @csrf
        <x-form.input name="code" label="Code TOTP" input-class="text-center fs-4 letter-spacing" maxlength="6"
            pattern="[0-9]{6}" autocomplete="one-time-code" autofocus placeholder="000000" />
        <x-form.input name="recovery_code" label="Ou code de secours" placeholder="XXXX-XXXX" />
        <button class="btn btn-ceeac w-100 mt-2"><i class="bi bi-unlock me-1"></i>Valider</button>
    </form>
    <div class="text-center mt-3"><a href="{{ route('login') }}" class="small">Retour à la connexion</a></div>
@endsection

@push('styles')
<style>.letter-spacing { letter-spacing: .35em; }</style>
@endpush
