@extends('layouts.app')

@section('title', 'Alamat Pengiriman')

@section('content')
<div class="page-shell py-8 sm:py-10">
    <x-page-header title="Alamat pengiriman" description="Simpan alamat yang dapat dipilih saat checkout." eyebrow="Akun pelanggan" />

    <div class="mt-8 grid items-start gap-8 lg:grid-cols-[1fr_380px]">
        <section class="space-y-4" aria-label="Daftar alamat">
            @forelse (($addresses ?? []) as $address)
                <article class="panel p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-extrabold text-ink-950">{{ data_get($address, 'label', data_get($address, 'name', 'Alamat')) }}</h2>
                                @if(data_get($address, 'is_primary'))<x-badge color="orange">Alamat utama</x-badge>@endif
                            </div>
                            <p class="mt-3 text-sm font-semibold text-ink-800">{{ data_get($address, 'recipient', data_get($address, 'recipient_name', data_get($address, 'name'))) }}</p>
                            <address class="mt-1 text-sm not-italic leading-6 text-ink-500">
                                {{ data_get($address, 'phone') }}<br>
                                {{ data_get($address, 'address', data_get($address, 'address_line1', data_get($address, 'street'))) }}<br>
                                @if(data_get($address, 'district')){{ data_get($address, 'district') }}<br>@endif
                                @if(data_get($address, 'address_line2')){{ data_get($address, 'address_line2') }}<br>@endif
                                {{ data_get($address, 'city') }}, {{ data_get($address, 'province') }} {{ data_get($address, 'postal_code', data_get($address, 'zip_code')) }}
                            </address>
                        </div>
                        @if(!data_get($address, 'is_primary'))
                            <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('customer.addresses.setPrimary') ? route('customer.addresses.setPrimary', $address) : route('customer.addresses.primary', $address) }}">
                                @csrf
                                @if(\Illuminate\Support\Facades\Route::has('customer.addresses.setPrimary')) @method('PATCH') @endif
                                <button type="submit" class="shrink-0 text-xs font-bold text-flame-700 hover:text-flame-800">Jadikan utama</button>
                            </form>
                        @endif
                    </div>

                    <details class="mt-5 border-t border-ink-100 pt-4">
                        <summary class="cursor-pointer text-sm font-bold text-ink-700">Edit alamat</summary>
                        <form method="POST" action="{{ route('customer.addresses.update', $address) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="address_id" value="{{ data_get($address, 'id') }}">
                            <x-input name="label" label="Label alamat" :value="data_get($address, 'label', data_get($address, 'name'))" placeholder="Rumah, kantor" required />
                            <x-input name="recipient" label="Nama penerima" :value="data_get($address, 'recipient', data_get($address, 'recipient_name', data_get($address, 'name')))" required />
                            <input type="hidden" name="recipient_name" value="{{ data_get($address, 'recipient', data_get($address, 'recipient_name')) }}">
                            <x-input name="phone" label="Nomor telepon" :value="data_get($address, 'phone')" required />
                            <x-input name="postal_code" label="Kode pos" :value="data_get($address, 'postal_code', data_get($address, 'zip_code'))" required />
                            <div class="sm:col-span-2"><x-input name="address" label="Alamat" :value="data_get($address, 'address', data_get($address, 'address_line1', data_get($address, 'street')))" required /></div>
                            <input type="hidden" name="address_line1" value="{{ data_get($address, 'address', data_get($address, 'address_line1')) }}">
                            <div class="sm:col-span-2"><x-input name="address_line2" label="Detail alamat" :value="data_get($address, 'address_line2')" /></div>
                            <x-input name="district" label="Kecamatan" :value="data_get($address, 'district')" required />
                            <x-input name="city" label="Kota/kabupaten" :value="data_get($address, 'city')" required />
                            <x-input name="province" label="Provinsi" :value="data_get($address, 'province')" required />
                            <label class="flex items-center gap-2.5 text-sm font-medium text-ink-700 sm:col-span-2">
                                <input type="checkbox" name="is_primary" value="1" class="h-4 w-4 rounded border-ink-300 text-flame-500 focus:ring-flame-300" @checked(data_get($address, 'is_primary'))>
                                Jadikan alamat utama
                            </label>
                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-100 pt-4 sm:col-span-2">
                                <x-button type="submit">Simpan</x-button>
                            </div>
                        </form>
                    </details>

                    <form method="POST" action="{{ route('customer.addresses.destroy', $address) }}" class="mt-4 flex justify-end border-t border-ink-100 pt-4" x-data="confirmAction('Hapus alamat ini?')" x-on:submit="confirm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-bold text-red-600 hover:text-red-700"><x-icon name="trash" class="h-4 w-4" /> Hapus alamat</button>
                    </form>
                </article>
            @empty
                <div class="panel">
                    <x-empty-state title="Belum ada alamat" description="Tambahkan alamat pengiriman pertama Anda." icon="map-pin" />
                </div>
            @endforelse
        </section>

        <aside class="panel p-5 lg:sticky lg:top-24" aria-labelledby="new-address-title">
            <h2 id="new-address-title" class="font-extrabold text-ink-950">Tambah alamat</h2>
            <form method="POST" action="{{ route('customer.addresses.store') }}" class="mt-5 space-y-4">
                @csrf
                <x-input name="label" label="Label alamat" placeholder="Rumah, kantor" />
                <x-input name="recipient" label="Nama penerima" required />
                <x-input name="phone" label="Nomor telepon" required />
                <x-input name="address" label="Alamat" required />
                <x-input name="address_line2" label="Detail alamat" />
                <x-input name="district" label="Kecamatan" required />
                <div class="grid grid-cols-2 gap-3">
                    <x-input name="city" label="Kota/kabupaten" required />
                    <x-input name="province" label="Provinsi" required />
                </div>
                <x-input name="postal_code" label="Kode pos" required />
                <label class="flex items-center gap-2.5 text-sm font-medium text-ink-700">
                    <input type="checkbox" name="is_primary" value="1" class="h-4 w-4 rounded border-ink-300 text-flame-500 focus:ring-flame-300" @checked(collect($addresses ?? [])->isEmpty())>
                    Jadikan alamat utama
                </label>
                <x-button type="submit" class="w-full"><x-icon name="plus" class="h-4 w-4" /> Tambah alamat</x-button>
            </form>
        </aside>
    </div>
</div>
@endsection
