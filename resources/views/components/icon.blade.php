@props(['name'=>'box'])
@php($paths = [
'box'=>'M21 8l-9 5-9-5m9 5v9M3 8l9-5 9 5v10l-9 5-9-5V8z',
'grid'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
'clock'=>'M12 8v5l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
'plus'=>'M12 5v14M5 12h14',
'in'=>'M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5',
'out'=>'M12 15V3m-5 5 5-5 5 5M4 16v5h16v-5',
'pin'=>'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
'alert'=>'M12 3 2 21h20L12 3z M12 9v5m0 3v1',
'qr'=>'M3 3h6v6H3z M15 3h6v6h-6z M3 15h6v6H3z M15 15h3v3h3v3h-6v-6z',
'settings'=>'M4 7h16M4 17h16M8 4v6M16 14v6',
'logout'=>'M9 4H4v16h5M10 12h12m-4-4 4 4-4 4',
'download'=>'M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4',
'search'=>'M21 21l-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
'menu'=>'M3 6h18M3 12h18M3 18h18',
'check'=>'m5 12 4 4L19 6',
])
<svg {{ $attributes->merge(['class'=>'h-5 w-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['box'] }}"/></svg>
