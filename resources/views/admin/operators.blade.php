@extends('layouts.admin')

@section('title', 'Operator')

@section('content')
<div class="space-y-7">
    <x-page-header title="Operator" description="Kelola akun operator produksi." eyebrow="Laporan" />

    <form method="GET" action="{{ \Illuminate\Support\Facades\Route::has('admin.operators') ? route('admin.operators') : route('admin.operators.index') }}" class="panel flex flex-col gap-3 p-4 sm:flex-row" aria-label="Cari operator">
        <div class="relative flex-1"><label for="search" class="sr-only">Cari operator</label><x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" /><input id="search" name="search" value="{{ request('search') }}" class="form-control pl-10" placeholder="Nama atau email operator"></div>
        <x-button type="submit"><x-icon name="filter" class="h-4 w-4" /> Cari</x-button>
    </form>

    <div class="panel overflow-hidden">
        @if (collect($operators ?? [])->isNotEmpty())
            <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Nama</th><th>Email</th><th>Pekerjaan</th><th>Bergabung</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead><tbody>
                @foreach($operators as $operator)
                    <tr>
                        <td class="font-semibold text-ink-900">{{ data_get($operator, 'user.name', data_get($operator, 'name', 'Operator')) }}</td>
                        <td>{{ data_get($operator, 'user.email', data_get($operator, 'email', 'Belum tersedia')) }}</td>
                        <td>{{ data_get($operator, 'production_orders_count', data_get($operator, 'jobs_count', 'Belum tersedia')) }}</td>
                        <td>{{ data_get($operator, 'created_at')?->format('d M Y') ?? 'Belum tersedia' }}</td>
                        <td><x-status-badge :status="data_get($operator, 'user.is_active') === false ? 'inactive' : 'active'" /></td>
                        <td class="text-right"><form method="POST" action="{{ route('admin.operators.toggle', data_get($operator, 'id')) }}">@csrf<x-button type="submit" size="sm" variant="secondary">Aktif/nonaktif</x-button></form></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            @if(method_exists($operators, 'links'))<div class="border-t border-ink-200 px-4 py-4">{{ $operators->withQueryString()->links() }}</div>@endif
        @else<x-empty-state title="Belum ada operator" description="Akun operator akan tampil setelah dibuat." icon="user-cog" />@endif
    </div>
</div>
@endsection
