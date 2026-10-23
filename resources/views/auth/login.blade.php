@extends('layouts.guest')
@section('titre', 'Connexion')
@section('sous-titre', 'Espace sécurisé · Connexion')

@section('contenu')
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <x-form.input name="email" label="Adresse e-mail" type="email" :value="old('email')" required autofocus />
        <x-form.input name="password" label="Mot de passe" type="password" required />
        <x-form.checkbox name="remember" label="Se souvenir de moi" />
        <button class="btn btn-ceeac w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Se connecter</button>
    </form>
@endsection
