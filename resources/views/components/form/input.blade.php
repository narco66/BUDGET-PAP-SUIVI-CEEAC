@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'help' => null,
    'col' => null,
    'id' => null,
    'disabled' => false,
    'placeholder' => null,
    'inputClass' => '',
    'errorKey' => null,
    'labelClass' => null,
])

@php
    $fieldId = $id ?? preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    $errorField = $errorKey ?? preg_replace(['/\]\[/', '/\[/', '/\]/'], ['.', '.', ''], $name);
    $invalid = $errors->has($errorField);
    $resolved = old($errorField, $value);
    $colClass = $col ? (is_numeric($col) ? "col-md-{$col}" : $col) : null;
@endphp

<div @class([$colClass])>
    @if ($label)
        <label for="{{ $fieldId }}" @class(['form-label', $labelClass])>
            {{ $label }}@if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $fieldId }}"
        value="{{ $type !== 'file' ? $resolved : '' }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        {{ $attributes->merge(['class' => trim('form-control '.($invalid ? 'is-invalid ' : '').$inputClass)]) }}
    />
    @error($errorField)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{!! $help !!}</div>
    @endif
</div>
