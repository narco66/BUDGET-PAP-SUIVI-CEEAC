@extends('layouts.guest')
@section('titre', 'Changer le mot de passe')
@section('sous-titre', 'Sécurité du compte')

@section('contenu')
    <h6 class="fw-bold text-center mb-3"><i class="bi bi-shield-lock me-1" style="color:var(--ceeac)"></i>Nouveau mot de passe</h6>

    @if($force)
        <div class="alert alert-warning py-2 small">Pour des raisons de sécurité, vous devez définir un nouveau mot de passe avant d'accéder à l'application.</div>
    @endif
    @if (session('erreur'))
        <div class="alert alert-danger py-2 small">{{ session('erreur') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf @method('PUT')
        <x-form.input name="mot_de_passe_actuel" label="Mot de passe actuel" type="password" :value="null" required autofocus />
        <x-form.input name="password" label="Nouveau mot de passe" type="password" :value="null" required
            help="8+ caractères, majuscules/minuscules et chiffres ; non réutilisé." />
        <x-form.input name="password_confirmation" label="Confirmation" type="password" :value="null" required />
        <button class="btn btn-ceeac w-100 mt-2"><i class="bi bi-check-lg me-1"></i>Enregistrer</button>
    </form>
    @unless($force)<div class="text-center mt-2"><a href="{{ route('dashboard') }}" class="small">Retour</a> · <a href="{{ route('mfa.setup') }}" class="small">MFA</a></div>@endunless
    <form method="POST" action="{{ route('logout') }}" class="text-center mt-2">@csrf<button class="btn btn-link btn-sm text-muted">Se déconnecter</button></form>
@endsection
