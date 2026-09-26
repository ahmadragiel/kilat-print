@extends('layouts.admin')

@section('title', $title ?? 'Master data')

@section('content')
@php
    $resourceType = $resourceType ?? request()->route('resourceType') ?? 'products';
    $resourceItems = $items ?? $resources ?? $records ?? [];
    $resourceLabels = ['products' => 'Produk', 'categories' => 'Kategori', 'materials' => 'Material', 'finishings' => 'Finishing'];
    $resourceLabel = $resourceLabel ?? $title ?? ($resourceLabels[$resourceType] ?? ucfirst($resourceType));
    $createUrl = \Illuminate\Support\Facades\Route::has('admin.resources.form')
        ? route('admin.resources.form', ['resourceType' => $resourceType])
        : route('admin.'.$resourceType.'.create');
@endphp

<div class="space-y-7">
    <x-page-header :title="$resourceLabel" :description="'Kelola data master ' . strtolower($resourceLabel) . '.'" eyebrow="Master data">
        <x-slot:actions>
            <x-button :href="$createUrl"><x-icon name="plus" class="h-4 w-4" /> Tambah {{ strtolower($resourceLabel) }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="panel overflow-hidden">
        @if (collect($resourceItems)->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ $resourceLabel }}</th>
                            @if($resourceType === 'products')<th>Kategori</th><th>Harga dasar</th><th>Status</th>@endif
                            @if($resourceType === 'categories')<th>Slug</th><th>Jumlah produk</th><th>Status</th>@endif
                            @if(in_array($resourceType, ['materials', 'finishings'], true))<th>Jenis harga</th><th>Harga</th><th>Status</th>@endif
                            <th><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resourceItems as $resource)
                            @php
                                $productPrice = data_get($resource, 'base_price');
                                $priceRules = data_get($resource, 'priceRules');
                                if ($productPrice === null && is_object($priceRules) && method_exists($priceRules, 'min')) {
                                    $productPrice = $priceRules->min('price');
                                }
                                $resourcePricingType = data_get($resource, 'pricing_type');
                                $resourcePricingLabel = $resourcePricingType instanceof \UnitEnum
                                    ? (method_exists($resourcePricingType, 'label') ? $resourcePricingType->label() : $resourcePricingType->name)
                                    : data_get($resource, 'pricing_type', 'Belum tersedia');
                                $editUrl = \Illuminate\Support\Facades\Route::has('admin.resources.form')
                                    ? route('admin.resources.form', ['resourceType' => $resourceType, 'resource' => $resource])
                                    : route('admin.'.$resourceType.'.edit', ['record' => $resource]);
                                $deleteUrl = \Illuminate\Support\Facades\Route::has('admin.resources.destroy')
                                    ? route('admin.resources.destroy', ['resourceType' => $resourceType, 'resource' => $resource])
                                    : route('admin.'.$resourceType.'.destroy', ['record' => $resource]);
                            @endphp
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        @if($resourceType === 'products')
                                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg border border-ink-200"><x-product-image :src="data_get($resource, 'thumbnail_url') ?? data_get($resource, 'image_url') ?? data_get($resource, 'thumbnail') ?? data_get($resource, 'image')" :alt="''" class="h-full w-full" /></div>
                                        @else
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-ink-100 text-ink-600"><x-icon :name="$resourceType === 'categories' ? 'category' : ($resourceType === 'materials' ? 'layers' : 'palette')" class="h-5 w-5" /></span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="font-bold text-ink-950">{{ data_get($resource, 'name', 'Tanpa nama') }}</p>
                                            @if(data_get($resource, 'description'))<p class="mt-0.5 max-w-xs truncate text-xs text-ink-500">{{ data_get($resource, 'description') }}</p>@endif
                                        </div>
                                    </div>
                                </td>
                                @if($resourceType === 'products')
                                    <td>{{ data_get($resource, 'category.name', 'Belum diatur') }}</td>
                                    <td class="font-extrabold text-brand-600"><x-money :value="$productPrice" /></td>
                                    <td><x-status-badge :status="data_get($resource, 'status')" /></td>
                                @endif
                                @if($resourceType === 'categories')
                                    <td class="font-mono text-xs">{{ data_get($resource, 'slug', 'Belum tersedia') }}</td>
                                    <td>{{ data_get($resource, 'products_count', 'Belum tersedia') }}</td>
                                    <td><x-status-badge :status="data_get($resource, 'status')" /></td>
                                @endif
                                @if(in_array($resourceType, ['materials', 'finishings'], true))
                                    <td>{{ $resourcePricingLabel }}</td>
                                    <td class="font-extrabold text-brand-600"><x-money :value="data_get($resource, 'price')" /></td>
                                    <td><x-status-badge :status="data_get($resource, 'status')" /></td>
                                @endif
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ $editUrl }}" class="action-link"><x-icon name="edit" class="h-4 w-4" /> <span class="hidden sm:inline">Edit</span></a>
                                        <form method="POST" action="{{ $deleteUrl }}" x-data="confirmAction('Hapus data master ini?')" x-on:submit="confirm">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="resourceType" value="{{ $resourceType }}">
                                            <button type="submit" class="grid h-9 w-9 place-items-center rounded-lg text-ink-500 transition hover:bg-danger-50 hover:text-danger-600" aria-label="Hapus {{ data_get($resource, 'name', $resourceLabel) }}"><x-icon name="trash" class="h-4 w-4" /></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (method_exists($resourceItems ?? [], 'links'))
                <div class="border-t border-ink-200 px-4 py-4">{{ $resourceItems->links() }}</div>
            @endif
        @else
            <x-empty-state :title="'Belum ada ' . strtolower($resourceLabel)" description="Tambahkan data untuk mulai mengelola katalog." :icon="$resourceType === 'categories' ? 'category' : ($resourceType === 'materials' ? 'layers' : 'box')">
                <x-button :href="$createUrl" size="sm"><x-icon name="plus" class="h-4 w-4" /> Tambah data</x-button>
            </x-empty-state>
        @endif
    </div>
</div>
@endsection
