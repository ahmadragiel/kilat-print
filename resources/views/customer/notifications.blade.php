@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
<div class="page-shell py-8 sm:py-10">
    <x-page-header title="Notifikasi" description="Pembaruan pembayaran, desain, dan produksi pesanan Anda." eyebrow="Akun pelanggan">
        <x-slot:actions>
            @if(\Illuminate\Support\Facades\Route::has('customer.notifications.read-all'))
                <form method="POST" action="{{ route('customer.notifications.read-all') }}">
                    @csrf
                    <x-button type="submit" variant="secondary" size="sm"><x-icon name="check" class="h-4 w-4" /> Tandai semua dibaca</x-button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="panel mt-8 overflow-hidden" aria-label="Daftar notifikasi">
        @if (collect($notifications ?? [])->isNotEmpty())
            <div class="divide-y divide-ink-100">
                @foreach ($notifications as $notification)
                    <article @class([
                        'flex gap-4 p-4 sm:p-5',
                        'bg-flame-50/40' => !data_get($notification, 'read_at'),
                    ])>
                        <span @class([
                            'mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-md',
                            'bg-flame-100 text-flame-700' => !data_get($notification, 'read_at'),
                            'bg-ink-100 text-ink-500' => (bool) data_get($notification, 'read_at'),
                        ])>
                            <x-icon :name="data_get($notification, 'icon', data_get($notification, 'type') === 'production' ? 'factory' : 'bell')" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <h2 class="font-bold text-ink-900">{{ data_get($notification, 'data.title', data_get($notification, 'title', 'Pembaruan pesanan')) }}</h2>
                                <time class="shrink-0 text-xs text-ink-400" @if(data_get($notification, 'created_at')) datetime="{{ data_get($notification, 'created_at')?->toIso8601String() }}" @endif>{{ data_get($notification, 'created_at')?->diffForHumans() ?? 'Waktu belum tersedia' }}</time>
                            </div>
                            <p class="mt-1 text-sm leading-6 text-ink-600">{{ data_get($notification, 'data.message', data_get($notification, 'message', data_get($notification, 'data.body', 'Tidak ada detail notifikasi.'))) }}</p>
                            @if(data_get($notification, 'data.url') || data_get($notification, 'data.order_id') || data_get($notification, 'order_id'))
                                <a href="{{ data_get($notification, 'data.url') ?: route('customer.orders.show', data_get($notification, 'data.order_id', data_get($notification, 'order_id'))) }}" class="action-link mt-3">Lihat pesanan <x-icon name="arrow-right" class="h-4 w-4" /></a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            @if (method_exists($notifications ?? [], 'links'))
                <div class="border-t border-ink-200 px-4 py-4">{{ $notifications->links() }}</div>
            @endif
        @else
            <x-empty-state title="Belum ada notifikasi" description="Pembaruan penting tentang pesanan akan tampil di sini." icon="bell" />
        @endif
    </section>
</div>
@endsection
