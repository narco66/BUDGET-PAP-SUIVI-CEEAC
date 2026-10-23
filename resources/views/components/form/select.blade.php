@props([
    'name',
    'label' => null,
    'value' => null,
    'options' => [],
    'required' => false,
    'help' => null,
    'col' => null,
    'id' => null,
    'disabled' => false,
    'placeholder' => null,
    'selectClass' => '',
    'errorKey' => null,
    'labelClass' => null,
])

@php
    $fieldId = $id ?? preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    $errorField = $errorKey ?? preg_replace(['/\]\[/', '/\[/', '/\]/'], ['.', '.', ''], $name);
    $invalid = $errors->has($errorField);
    $selected = old($errorField, $value);
    $colClass = $col ? (is_numeric($col) ? "col-md-{$col}" : $col) : null;
    $hasSlot = ! $slot->isEmpty();
@endphp

<div @class([$colClass])>
    @if ($label)
        <label for="{{ $fieldId }}" @class(['form-label', $labelClass])>
            {{ $label }}@if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif
    <select
        name="{{ $name }}"
        id="{{ $fieldId }}"
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        {{ $attributes->merge(['class' => trim('form-select '.($invalid ? 'is-invalid ' : '').$selectClass)]) }}
    >
        @if ($placeholder !== false)
            <option value="">{{ $placeholder ?? '— Choisir —' }}</option>
        @endif
        @if ($hasSlot)
            {{ $slot }}
        @else
            @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $selected === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        @endif
    </select>
    @error($errorField)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{!! $help !!}</div>
    @endif
</div>
