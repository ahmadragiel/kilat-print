@props([
    'inverse' => false,
    'size' => 'md',
])

{{--
    Logo Kilat Print. Kedua file berlatar transparan (tanpa kotak putih):
      - `public/images/logo.png`       : merah tua + kilat kuning, untuk latar terang
      - `public/images/logo-white.png` : knockout putih + kilat kuning, untuk panel
                                         merah/footer gelap (varian `inverse`)

    Logo disisipkan langsung di atas latar halaman, jadi tidak ada chip/kotak
    putih lagi. Rasio tetap dijaga lewat atribut intrinsik `width`/`height`
    (772x413 = 1.87:1) + `max-h-*` + `w-auto` + `object-contain`, sehingga logo
    tidak pernah gepeng, tidak meluber, dan selalu tajam (file 772px dipakai pada
    tinggi 32-56px, jadi hanya downscale).
--}}
@php
    $logoSizes = [
        'sm' => 'max-h-8',
        'md' => 'max-h-10',
        'lg' => 'max-h-14',
    ];
    $logoMaxHeight = $logoSizes[$size] ?? $logoSizes['md'];
    $logoSrc = asset($inverse ? 'images/logo-white.png' : 'images/logo.png');
@endphp

<a href="/" {{ $attributes->class('inline-flex max-w-full items-center rounded-xl transition') }}>
    <img
        src="{{ $logoSrc }}"
        alt="Kilat Print"
        width="772"
        height="413"
        class="{{ $logoMaxHeight }} h-auto w-auto max-w-full object-contain align-middle"
        decoding="async"
    >
</a>