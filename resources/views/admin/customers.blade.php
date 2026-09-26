@extends('layouts.admin')

@section('title', 'Pelanggan')

@section('content')
<div class="space-y-7">
    <x-page-header title="Pelanggan" description="Daftar akun pelanggan yang tersimpan di sistem." eyebrow="Laporan" />

    <form method="GET" action="{{ \Illuminate\Support\Facades\Route::has('admin.customers') ? route('admin.customers') : route('admin.customers.index') }}" class="panel flex flex-col gap-3 p-4 sm:flex-row" aria-label="Cari pelanggan">
        <div class="relative flex-1"><label for="search" class="sr-only">Cari pelanggan</label><x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" /><input id="search" name="search" value="{{ request('search') }}" class="form-control pl-10" placeholder="Nama atau email pelanggan"></div>
        <x-button type="submit"><x-icon name="filter" class="h-4 w-4" /> Cari</x-button>
    </form>

    <div class="panel overflow-hidden">
        @if (collect($customers ?? [])->isNotEmpty())
            <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Pelanggan</th><th>Kontak</th><th>Pesanan</th><th>Bergabung</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead><tbody>
                @foreach($customers as $customer)
                    <tr>
                        <td><div class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-md bg-ink-950 text-xs font-bold text-white">{{ strtoupper(substr(data_get($customer, 'user.name', data_get($customer, 'name', 'P')), 0, 1)) }}</span><span class="font-semibold text-ink-900">{{ data_get($customer, 'user.name', data_get($customer, 'name', 'Pelanggan')) }}</span></div></td>
                        <td><p>{{ data_get($customer, 'user.email', data_get($customer, 'email', 'Belum tersedia')) }}</p><p class="mt-1 text-xs text-ink-500">{{ data_get($customer, 'user.phone', data_get($customer, 'phone', 'Belum ada telepon')) }}</p></td>
                        <td>{{ data_get($customer, 'orders_count', 'Belum tersedia') }}</td>
                        <td>{{ data_get($customer, 'created_at')?->format('d M Y') ?? 'Belum tersedia' }}</td>
                        <td><x-status-badge :status="data_get($customer, 'user.is_active') === false ? 'inactive' : 'active'" /></td>
                        <td class="text-right"><form method="POST" action="{{ route('admin.customers.toggle', data_get($customer, 'id')) }}">@csrf<x-button type="submit" size="sm" variant="secondary">Aktif/nonaktif</x-button></form></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            @if(method_exists($customers, 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $customers->withQueryString()->links() }}</div>@endif
        @else<x-empty-state title="Pelanggan tidak ditemukan" description="Belum ada pelanggan yang sesuai pencarian." icon="users" />@endif
    </div>
</div>
@endsection
