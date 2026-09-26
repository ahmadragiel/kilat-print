@extends('layouts.admin')

@section('title', 'Harga Produk')

@section('content')
@php
    $priceItems = $prices ?? $priceRules ?? [];
    $editRoute = fn ($priceRule) => \Illuminate\Support\Facades\Route::has('admin.prices.form')
        ? route('admin.prices.form', $priceRule)
        : route('admin.prices.edit', $priceRule);
    $deleteRoute = fn ($priceRule) => \Illuminate\Support\Facades\Route::has('admin.prices.destroy')
        ? route('admin.prices.destroy', $priceRule)
        : route('admin.prices.destroy', $priceRule);
@endphp

<div class="space-y-7">
    <x-page-header title="Harga produk" description="Kelola aturan harga dari database." eyebrow="Master data">
        <x-slot:actions>
            <x-button :href="\Illuminate\Support\Facades\Route::has('admin.prices.form') ? route('admin.prices.form') : route('admin.prices.create')"><x-icon name="plus" class="h-4 w-4" /> Tambah harga</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="panel overflow-hidden">
        @if (collect($priceItems)->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Produk</th><th>Nama aturan</th><th>Jenis harga</th><th>Minimum</th><th>Harga</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody>
                        @foreach($priceItems as $priceRule)
                            @php
                                $pricingType = data_get($priceRule, 'pricing_type');
                                $pricingLabel = $pricingType instanceof \UnitEnum
                                    ? (method_exists($pricingType, 'label') ? $pricingType->label() : $pricingType->name)
                                    : data_get($priceRule, 'pricing_type', 'Belum tersedia');
                            @endphp
                            <tr>
                                <td class="font-semibold text-ink-900">{{ data_get($priceRule, 'product.name', data_get($priceRule, 'product_name', 'Produk')) }}</td>
                                <td>{{ data_get($priceRule, 'name', 'Aturan harga') }}</td>
                                <td>{{ $pricingLabel }}</td>
                                <td>{{ data_get($priceRule, 'min_quantity', 'Tidak ada') }}</td>
                                <td class="font-bold text-ink-900"><x-money :value="data_get($priceRule, 'price', data_get($priceRule, 'amount'))" /></td>
                                <td><x-status-badge :status="data_get($priceRule, 'active') === false ? 'inactive' : 'active'" /></td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ $editRoute($priceRule) }}" class="action-link"><x-icon name="edit" class="h-4 w-4" /> <span class="hidden sm:inline">Edit</span></a>
                                        <form method="POST" action="{{ $deleteRoute($priceRule) }}" x-data="confirmAction('Hapus aturan harga ini?')" x-on:submit="confirm">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="grid h-9 w-9 place-items-center rounded-md text-ink-400 transition hover:bg-red-50 hover:text-red-600" aria-label="Hapus aturan harga"><x-icon name="trash" class="h-4 w-4" /></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (method_exists($priceItems ?? [], 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $priceItems->links() }}</div>@endif
        @else
            <x-empty-state title="Belum ada aturan harga" description="Tambahkan aturan harga produk." icon="calculator">
                <x-button :href="\Illuminate\Support\Facades\Route::has('admin.prices.form') ? route('admin.prices.form') : route('admin.prices.create')" size="sm">Tambah harga</x-button>
            </x-empty-state>
        @endif
    </div>
</div>
@endsection
