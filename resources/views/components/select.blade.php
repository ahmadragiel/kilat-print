@props([
    'name',
    'id' => null,
    'label' => null,
    'value' => null,
    'placeholder' => null,
    'help' => null,
    'required' => false,
    'error' => null,
])

@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $selectValue = old($name, $value);
    $selectError = $error ?: $errors->first($name);
    $selectId = $id ?? $name;
    $describedBy = trim(($help && ! $selectError ? $selectId.'-help ' : '').($selectError ? $selectId.'-error' : ''));
@endphp

<div {{ $attributes->class(['w-full']) }}>
    @if ($label)
        <label for="{{ $selectId }}" class="form-label">
            {{ $label }}
            @if($required)<span class="text-danger-600" aria-hidden="true">*</span>@endif
        </label>
    @endif
    <select
        id="{{ $selectId }}"
        name="{{ $name }}"
        x-data
        @if(!$attributes->has('multiple'))
            x-init='this.value = @json($selectValue, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)'
        @endif
        @required($required)
        @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if($selectError) aria-invalid="true" @endif
        {{ $attributes->class(['form-control', 'form-control-error' => (bool) $selectError]) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot ?? '' }}
    </select>
    @if ($help && !$selectError)
        <p id="{{ $selectId }}-help" class="mt-1.5 text-xs text-ink-500">{{ $help }}</p>
    @endif
    @if ($selectError)
        <p id="{{ $selectId }}-error" class="mt-1.5 text-xs font-medium text-danger-600">{{ $selectError }}</p>
    @endif
</div>
