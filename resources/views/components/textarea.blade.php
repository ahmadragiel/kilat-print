@props([
    'name',
    'label' => null,
    'value' => null,
    'placeholder' => null,
    'help' => null,
    'required' => false,
    'error' => null,
    'rows' => 4,
])

@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $textareaValue = old($name, $value);
    $textareaError = $error ?: $errors->first($name);
@endphp

<div {{ $attributes->class(['w-full']) }}>
    @if ($label)
        <label for="{{ $name }}" class="form-label">
            {{ $label }}
            @if($required)<span class="text-danger-600" aria-hidden="true">*</span>@endif
        </label>
    @endif
    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @required($required)
        @if($textareaError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class(['form-control resize-y', 'form-control-error' => (bool) $textareaError]) }}
    >{{ $textareaValue }}</textarea>
    @if ($help && !$textareaError)
        <p class="mt-1.5 text-xs text-ink-500">{{ $help }}</p>
    @endif
    @if ($textareaError)
        <p id="{{ $name }}-error" class="mt-1.5 text-xs font-medium text-danger-600">{{ $textareaError }}</p>
    @endif
</div>
