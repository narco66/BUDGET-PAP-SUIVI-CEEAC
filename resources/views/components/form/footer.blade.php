@props([
    'cancelUrl',
    'cancelLabel' => 'Annuler',
    'submitLabel' => 'Enregistrer',
    'submitIcon' => 'bi-check-lg',
])

<div {{ $attributes->merge(['class' => 'card-footer bg-white']) }}>
    <div class="d-flex gap-2 justify-content-end flex-wrap">
        <a href="{{ $cancelUrl }}" class="btn btn-light">{{ $cancelLabel }}</a>
        <button type="submit" class="btn btn-primary">
            @if ($submitIcon)<i class="bi {{ $submitIcon }} me-1"></i>@endif{{ $submitLabel }}
        </button>
        {{ $slot }}
    </div>
</div>
