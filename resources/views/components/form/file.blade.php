@props([
    'name',
    'label' => null,
    'required' => false,
    'help' => null,
    'col' => null,
    'id' => null,
    'multiple' => false,
])

@php
    $fieldId = $id ?? $name;
    $invalid = $errors->has($name) || $errors->has($name.'.*');
    $colClass = $col ? (is_numeric($col) ? "col-md-{$col}" : $col) : null;
@endphp

<div @class([$colClass])>
    @if ($label)
        <label for="{{ $fieldId }}" class="form-label">
            {{ $label }}@if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif
    <input
        type="file"
        name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        id="{{ $fieldId }}"
        @if ($multiple) multiple @endif
        @if ($required) required @endif
        {{ $attributes->class(['form-control', 'is-invalid' => $invalid]) }}
    />
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error($name.'.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{!! $help !!}</div>
    @endif
</div>
