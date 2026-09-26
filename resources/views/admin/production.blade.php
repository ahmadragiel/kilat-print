@extends('layouts.admin')

@section('title', 'Produksi')

@section('content')
@php $productionItems = $productions ?? $jobs ?? []; @endphp
<div class="space-y-7">
    <x-page-header title="Produksi" description="Tugaskan pekerjaan dan pantau tahap produksi." eyebrow="Operasional">
        <x-slot:actions><x-button :href="route('admin.orders.index')" variant="secondary"><x-icon name="shopping-bag" class="h-4 w-4" /> Semua pesanan</x-button></x-slot:actions>
    </x-page-header>

    <div class="panel overflow-hidden">
        @if (collect($productionItems)->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Operator</th><th>Tahap</th><th>Deadline</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach($productionItems as $production)
                            @php
                                $assignUrl = \Illuminate\Support\Facades\Route::has('production.assign') ? route('production.assign', $production) : route('admin.production.assign', $production);
                                $operatorId = data_get($production, 'operator_id');
                            @endphp
                            <tr>
                                <td class="font-semibold text-ink-900">#{{ data_get($production, 'order.number', data_get($production, 'order_number', data_get($production, 'order_id'))) }}</td>
                                <td>{{ data_get($production, 'order.customer.user.name', data_get($production, 'customer_name', 'Pelanggan')) }}</td>
                                <td>{{ data_get($production, 'operator.user.name', data_get($production, 'operator_name', 'Belum ditugaskan')) }}</td>
                                <td><x-status-badge :status="data_get($production, 'status')" /></td>
                                <td>@if(data_get($production, 'deadline'))<span class="inline-flex items-center gap-1.5 rounded-full bg-accent-100 px-2.5 py-1 text-xs font-bold text-accent-800 ring-1 ring-inset ring-accent-300"><x-icon name="clock" class="h-3.5 w-3.5" /> {{ data_get($production, 'deadline')->format('d M Y') }}</span>@else<span class="text-ink-500">Belum ditentukan</span>@endif</td>
                                <td>
                                    <form method="POST" action="{{ $assignUrl }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <select name="operator_id" class="form-control min-h-9 min-w-36 py-1.5 text-xs" required aria-label="Operator untuk pesanan #{{ data_get($production, 'order.number', data_get($production, 'id')) }}">
                                            <option value="">Pilih operator</option>
                                            @foreach(($operators ?? []) as $operator)
                                                <option value="{{ data_get($operator, 'id') }}" @selected((string) $operatorId === (string) data_get($operator, 'id'))>{{ data_get($operator, 'user.name', data_get($operator, 'name', 'Operator')) }}</option>
                                            @endforeach
                                        </select>
                                        <input name="deadline" type="date" value="{{ data_get($production, 'deadline')?->format('Y-m-d') }}" class="form-control min-h-9 w-36 py-1.5 text-xs" aria-label="Deadline">
                                        <x-button type="submit" size="sm" variant="secondary">Simpan</x-button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($productionItems, 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $productionItems->links() }}</div>@endif
        @else
            <x-empty-state title="Belum ada pekerjaan produksi" description="Pesanan yang siap diproses akan tampil di sini." icon="factory" />
        @endif
    </div>
</div>
@endsection
