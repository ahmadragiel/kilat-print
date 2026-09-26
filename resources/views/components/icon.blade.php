@props([
    'name' => 'circle',
    'class' => 'h-5 w-5',
])

<svg {{ $attributes->class([$class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('home')
            <path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9"/><path d="M9 20v-6h6v6"/>
            @break
        @case('category')
            <path d="M4 5a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M4 9h16"/>
            @break
        @case('box')
            <path d="m21 8-9 5-9-5"/><path d="m3 8 9-5 9 5v8l-9 5-9-5Z"/><path d="M12 13v8"/>
            @break
        @case('layers')
            <path d="m12 2 9 5-9 5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>
            @break
        @case('printer')
            <path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/>
            @break
        @case('cart')
            <circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H6"/>
            @break
        @case('shopping-bag')
            <path d="M6 8h12l1 13H5Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>
            @break
        @case('credit-card')
            <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h2"/>
            @break
        @case('palette')
            <path d="M12 3a9 9 0 0 0 0 18h1.5a1.5 1.5 0 0 0 0-3H12a2 2 0 0 1 0-4h2a7 7 0 0 0 0-14Z"/><circle cx="7.5" cy="10.5" r="1" fill="currentColor" stroke="none"/><circle cx="10" cy="6.5" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="6.5" r="1" fill="currentColor" stroke="none"/>
            @break
        @case('factory')
            <path d="M3 21V9l6 4V9l6 4V5h4l2 16Z"/><path d="M7 17h2M13 17h2M18 17h2"/>
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            @break
        @case('user')
            <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
            @break
        @case('user-cog')
            <circle cx="9" cy="7" r="4"/><path d="M2 21a7 7 0 0 1 11.7-5.2M16 11v2M16 17v2M14 13h4M19.5 14.5l-1.7 1M12.5 14.5l1.7 1"/>
            @break
        @case('chart')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
            @break
        @case('receipt')
            <path d="M6 2h12v20l-3-2-3 2-3-2-3 2Z"/><path d="M9 7h6M9 11h6M9 15h4"/>
            @break
        @case('clipboard')
            <rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 4a3 3 0 0 1 6 0v2H9Z"/><path d="M9 12h6M9 16h6"/>
            @break
        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>
            @break
        @case('logout')
            <path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('x')
            <path d="m6 6 12 12M18 6 6 18"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('edit')
            <path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>
            @break
        @case('trash')
            <path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6M14 11v6"/>
            @break
        @case('eye')
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>
            @break
        @case('upload')
            <path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 15v5h14v-5"/>
            @break
        @case('download')
            <path d="M12 4v12M7 11l5 5 5-5"/><path d="M5 20h14"/>
            @break
        @case('external')
            <path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/>
            @break
        @case('check')
            <path d="m5 12 4 4L19 6"/>
            @break
        @case('check-circle')
            <circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>
            @break
        @case('x-circle')
            <circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('alert')
            <path d="M10.3 3.7 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>
            @break
        @case('info')
            <circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>
            @break
        @case('arrow-right')
            <path d="M5 12h14M14 7l5 5-5 5"/>
            @break
        @case('chevron-right')
            <path d="m9 18 6-6-6-6"/>
            @break
        @case('map-pin')
            <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>
            @break
        @case('phone')
            <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c1 .4 1.9.6 2.9.7a2 2 0 0 1 1.6 2Z"/>
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>
            @break
        @case('package')
            <path d="m16.5 9.4-9-5.2M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>
            @break
        @case('truck')
            <path d="M3 6h11v11H3ZM14 10h4l3 3v4h-7Z"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>
            @break
        @case('wallet')
            <path d="M4 5h14a2 2 0 0 1 2 2v12H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/><path d="M16 11h6v5h-6a2.5 2.5 0 0 1 0-5Z"/>
            @break
        @case('filter')
            <path d="M4 5h16M7 12h10M10 19h4"/>
            @break
        @case('refresh')
            <path d="M20 7h-5V2"/><path d="M20 7a9 9 0 1 0 1 8"/>
            @break
        @case('image')
            <rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 20"/>
            @break
        @case('file')
            <path d="M6 2h8l4 4v16H6Z"/><path d="M14 2v5h5M9 13h6M9 17h6"/>
            @break
        @case('calculator')
            <rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8v4H8ZM8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/>
            @break
        @case('play')
            <path d="m8 5 11 7-11 7Z"/>
            @break
        @case('scissors')
            <circle cx="6" cy="7" r="3"/><circle cx="6" cy="17" r="3"/><path d="m8.6 8.5 11.4 6.5M8.6 15.5 20 9"/>
            @break
        @case('shield')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('list')
            <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
            @break
        @case('sparkles')
            <path d="M12 2c1.2 5.2 3.8 7.8 9 9-5.2 1.2-7.8 3.8-9 9-1.2-5.2-3.8-7.8-9-9 5.2-1.2 7.8-3.8 9-9Z"/><path d="M19 16c.5 2.2 1.8 3.5 4 4-2.2.5-3.5 1.8-4 4-.5-2.2-1.8-3.5-4-4 2.2-.5 3.5-1.8 4-4Z"/>
            @break
        @case('star')
            <path d="m12 2 3 6 7 .9-5 4.7 1.3 6.9L12 17.2l-6.3 3.3L7 13.6 2 8.9 9 8Z"/>
            @break
        @case('zap')
            <path d="M13.2 2 5 13h6l-.8 9L19 10h-6l.2-8Z"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
