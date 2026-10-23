@extends('layouts.app')
@section('titre', 'Préférences notifications')

@section('contenu')
<div class="mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-sliders me-2"></i>Préférences de notifications</h4>
    <span class="text-muted small">Canaux de réception pour votre compte</span>
</div>

@if (session('succes'))<div class="alert alert-success">{{ session('succes') }}</div>@endif

<form method="POST" action="{{ route('profil.notifications.update') }}" class="card border-0 shadow-sm">
    @csrf @method('PUT')
    <div class="card-body">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="actif" value="1" id="actif" @checked(old('actif', $prefs['actif'] ?? true))>
            <label class="form-check-label" for="actif">Recevoir les notifications</label>
        </div>
        <hr>
        <p class="fw-semibold mb-2">Canaux autorisés</p>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="database" value="1" id="database" @checked(old('database', $prefs['canaux']['database'] ?? true))>
            <label class="form-check-label" for="database">Centre in-app</label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="email" value="1" id="email" @checked(old('email', $prefs['canaux']['email'] ?? true))>
            <label class="form-check-label" for="email">Courriel ({{ auth()->user()->email }})</label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="sms" value="1" id="sms" @checked(old('sms', $prefs['canaux']['sms'] ?? false))>
            <label class="form-check-label" for="sms">SMS @if(auth()->user()->telephone)({{ auth()->user()->telephone }})@else<span class="text-warning">— renseignez votre téléphone</span>@endif</label>
        </div>
        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="sms_critique_uniquement" value="1" id="sms_critique" @checked(old('sms_critique_uniquement', $prefs['sms_critique_uniquement'] ?? true))>
            <label class="form-check-label" for="sms_critique">SMS réservés aux alertes critiques / urgence</label>
        </div>
    </div>
    <div class="card-footer bg-white">
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
</form>
@endsection
