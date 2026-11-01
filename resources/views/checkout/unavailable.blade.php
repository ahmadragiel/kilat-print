@extends('layouts.app')

@section('title', 'Checkout belum tersedia')

@section('content')
<div class="page-shell py-10 sm:py-14">
    <div class="mx-auto max-w-xl">
        <x-page-header title="Checkout belum tersedia" description="Tarif pengiriman belum dikonfigurasi oleh admin." eyebrow="Checkout" />

        <div class="panel mt-7 p-6 sm:p-8">
            <div class="flex items-start gap-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-accent-100 text-accent-800">
                    <x-icon name="truck" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="font-extrabold text-ink-950">Pesanan belum bisa diproses</h2>
                    <p class="mt-2 text-sm leading-7 text-ink-600">
                        Ongkos kirim wajib dihitung dari tarif server. Karena tarif belum diatur, checkout ditutup sementara agar pesanan tidak tercatat dengan biaya kirim yang salah.
                    </p>
                    <p class="mt-3 text-sm leading-7 text-ink-600">
                        Hubungi admin Kilat Print untuk melengkapi tarif pengiriman. Item di keranjang Anda tetap tersimpan.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3 border-t border-ink-200 pt-5">
                <x-button :href="$cartUrl ?? route('cart.index')" variant="secondary">Kembali ke keranjang</x-button>
                <x-button :href="route('products.index')" variant="outline">Lihat katalog</x-button>
            </div>
        </div>
    </div>
</div>
@endsection
