@props([
    'name',
    'label' => null,
    'value' => null,
    'type' => 'text',
    'placeholder' => null,
    'help' => null,
    'required' => false,
    'error' => null,
    'autocomplete' => null,
])

@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $inputValue = old($name, $value);
    $inputError = $error ?: $errors->first($name);
@endphp

<div {{ $attributes->class(['w-full']) }}>
    @if ($label)
        <label for="{{ $name }}" class="form-label">
            {{ $label }}
            @if($required)<span class="text-danger-600" aria-hidden="true">*</span>@endif
        </label>
    @endif
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $inputValue }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @required($required)
        @if($inputError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class(['form-control', 'form-control-error' => (bool) $inputError]) }}
    >
    @if ($help && !$inputError)
        <p id="{{ $name }}-help" class="mt-1.5 text-xs text-ink-500">{{ $help }}</p>
    @endif
    @if ($inputError)
        <p id="{{ $name }}-error" class="mt-1.5 text-xs font-medium text-danger-600">{{ $inputError }}</p>
    @endif
</div>
