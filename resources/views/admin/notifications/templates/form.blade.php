@extends('layouts.app')
@section('titre', 'Modifier modèle e-mail')

@section('contenu')
<div class="mb-3">
    <a href="{{ route('admin.notifications.templates.index') }}" class="text-muted small text-decoration-none"><i class="bi bi-arrow-left"></i> Modèles e-mail</a>
    <h4 class="fw-bold mb-0">Modifier — <code>{{ $template->code }}</code></h4>
</div>

<form method="POST" action="{{ route('admin.notifications.templates.update', $template) }}">
    @csrf @method('PUT')
    <div class="card border-0 shadow-sm">
        <div class="card-body row g-3">
            <x-form.input name="libelle" label="Libellé" col="12" :value="old('libelle', $template->libelle)" required />
            <x-form.input name="sujet" label="Sujet" col="12" :value="old('sujet', $template->sujet)" required help="Variables : @{{ titre }}, @{{ message }}, @{{ numero }}, etc." />
            <x-form.textarea name="corps_html" label="Corps HTML" col="12" :value="old('corps_html', $template->corps_html)" :rows="10" required class="font-monospace" />
            <x-form.textarea name="corps_texte" label="Corps texte (facultatif)" col="12" :value="old('corps_texte', $template->corps_texte)" :rows="5" class="font-monospace" />
            <x-form.checkbox name="actif" label="Modèle actif" col="12" :checked="old('actif', $template->actif)" />
        </div>
        <x-form.footer :cancel-url="route('admin.notifications.templates.index')" />
    </div>
</form>
@endsection
