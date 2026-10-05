@extends('layouts.app')
@section('title','Transaksi Pending')
@section('content')
<div class="page-head"><div><h1>Transaksi Pending</h1><p class="mt-2 text-slate-500">Periksa barang dan quantity sebelum menyetujui.</p></div><a href="{{ route('scanner') }}" class="btn-light"><x-icon name="qr"/>Buka Scanner</a></div>
<div class="rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900">Scan mengunci QR. Stok berubah setelah Anda menekan <strong>Approve</strong>.</div>
@include('partials.pending')
@endsection
