<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="pending-url" content="{{ route('api.pending') }}"><meta name="api-base" content="{{ url('/api') }}"><title>@yield('title', 'Dashboard') · Warehouse Stock</title><link rel="stylesheet" href="{{ asset('assets/app.css') }}"><script defer src="{{ asset('assets/app.js') }}"></script>@stack('head')</head>
<body>
@php($pendingCount = \App\Models\StockTransaction::where('status','PENDING')->count())
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col overflow-y-auto border-r border-slate-200 bg-white p-5 lg:flex">
<a href="{{ route('dashboard') }}" class="mb-10 mt-3 flex items-center gap-3"><span class="rounded-xl bg-brand p-2.5 text-white"><x-icon name="box"/></span><span class="font-semibold leading-tight text-slate-950">WAREHOUSE<span class="block text-xs font-normal tracking-widest text-slate-400">STOCK MANAGEMENT</span></span></a>
<div class="mb-3 px-3 text-xs font-semibold uppercase tracking-widest text-slate-400">Operasional</div>
<nav class="space-y-1">
@foreach([
['dashboard','Dashboard','grid',route('dashboard')],['pending','Transaksi Pending','clock',route('pending')],['products.index','Semua Barang','box',route('products.index')],['products.create','Tambah Barang','plus',route('products.create')],['history.in','Riwayat Masuk','in',route('history',['type'=>'in'])],['history.out','Riwayat Keluar','out',route('history',['type'=>'out'])],['low','Stok Menipis','alert',route('products.index',['stock_status'=>'low'])]
] as [$key,$label,$icon,$url])
<a href="{{ $url }}" class="nav-link {{ url()->full()===$url ? 'active':'' }}"><x-icon :name="$icon"/><span>{{ $label }}</span>@if($key==='pending')<span data-pending-count class="ml-auto rounded-md bg-amber-100 px-1.5 text-xs text-amber-800">{{ $pendingCount }}</span>
@endif</a>
@endforeach
</nav>
<div class="mb-3 mt-7 px-3 text-xs font-semibold uppercase tracking-widest text-slate-400">Pengelolaan</div>
<nav class="space-y-1">
@foreach([['locations','Lokasi Gudang','pin'],['categories','Kategori','grid'],['adjustments','Stock Adjustment','settings'],['settings','Pengaturan','settings']] as [$key,$label,$icon])
<a href="{{ route($key) }}" class="nav-link {{ request()->routeIs($key) ? 'active':'' }}"><x-icon :name="$icon"/>{{ $label }}</a>
@endforeach
</nav>
<div class="mt-auto pt-7"><a class="btn-light w-full" href="{{ route('scanner') }}"><x-icon name="qr"/>Buka Scanner</a><form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="nav-link w-full"><x-icon name="logout"/>Logout</button></form></div>
</aside>
<div class="lg:ml-60"><header class="flex h-20 items-center justify-between border-b border-slate-200 bg-white px-5 md:px-9"><div class="flex items-center gap-3"><button id="menu-toggle" class="btn-light btn-small lg:hidden" aria-label="Buka navigasi" aria-expanded="false"><x-icon name="menu"/></button><span class="text-sm text-slate-500">Gudang <span class="mx-2 text-slate-300">/</span> <span class="font-medium text-slate-800">@yield('title','Dashboard')</span></span></div><div class="flex items-center gap-3"><span class="hidden text-sm text-slate-500 sm:block">{{ now()->translatedFormat('d F Y') }} · WIB</span><span class="flex h-9 w-9 items-center justify-center rounded-full bg-teal-50 text-sm font-semibold text-teal-800">{{ mb_substr(auth()->user()->name,0,1) }}</span></div></header>
<main class="mx-auto max-w-[1600px] p-5 md:p-9">
@if(session('success'))<div role="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('success') }}</div>
@endif
@if($errors->any())<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach</ul></div>
@endif
@yield('content')
</main></div><div id="toast" role="status" aria-live="polite" class="fixed bottom-5 right-5 z-50 hidden max-w-sm rounded-xl bg-slate-900 p-4 text-white shadow-lg"></div>@stack('scripts')
</body></html>
