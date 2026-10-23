@props([
    'name',
    'label' => null,
    'value' => null,
    'required' => false,
    'help' => null,
    'col' => null,
    'id' => null,
    'disabled' => false,
    'rows' => 3,
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
    <textarea
        name="{{ $name }}"
        id="{{ $fieldId }}"
        rows="{{ $rows }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        {{ $attributes->merge(['class' => trim('form-control '.($invalid ? 'is-invalid ' : '').$inputClass)]) }}
    >{{ $resolved }}</textarea>
    @error($errorField)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{!! $help !!}</div>
    @endif
</div>
