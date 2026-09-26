@props([
    'value',
])

@if ($value === null || $value === '')
    <span class="text-ink-500">Belum tersedia</span>
@elseif (is_numeric($value))
    <span>Rp {{ number_format((float) $value, 0, ',', '.') }}</span>
@else
    <span>{{ $value }}</span>
@endif
