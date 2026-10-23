@props([
    'name',
    'label',
    'checked' => false,
    'value' => '1',
    'col' => null,
    'id' => null,
    'help' => null,
])

@php
    $fieldId = $id ?? $name;
    $invalid = $errors->has($name);
    $isChecked = (bool) old($name, $checked);
    $colClass = $col ? (is_numeric($col) ? "col-md-{$col}" : $col) : null;
@endphp

<div @class([$colClass, 'd-flex align-items-end' => $col])>
    <div class="form-check">
        <input
            type="checkbox"
            name="{{ $name }}"
            id="{{ $fieldId }}"
            value="{{ $value }}"
            @checked($isChecked)
            {{ $attributes->class(['form-check-input', 'is-invalid' => $invalid]) }}
        />
        <label for="{{ $fieldId }}" class="form-check-label">{{ $label }}</label>
        @error($name)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @if ($help)
            <div class="form-text">{!! $help !!}</div>
        @endif
    </div>
</div>
