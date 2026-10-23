@props([
    'cancelUrl',
    'cancelLabel' => 'Annuler',
    'submitLabel' => 'Enregistrer',
    'submitIcon' => 'bi-check-lg',
])

<div {{ $attributes->class(['mt-3', 'd-flex', 'gap-2', 'flex-wrap']) }}>
    <button type="submit" class="btn btn-primary">
        @if ($submitIcon)<i class="bi {{ $submitIcon }} me-1"></i>@endif{{ $submitLabel }}
    </button>
    <a href="{{ $cancelUrl }}" class="btn btn-light">{{ $cancelLabel }}</a>
    {{ $slot }}
</div>
