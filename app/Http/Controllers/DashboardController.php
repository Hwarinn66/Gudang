<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockTransaction;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $products = Product::where('active', true);
        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $approved = StockTransaction::where('status', 'APPROVED')->where('approved_at', '>=', $today)->where('approved_at', '<', $tomorrow);
        $stats = ['Total jenis barang' => (clone $products)->count(), 'Total stok' => (clone $products)->sum('stock'), 'Barang IN hari ini' => (clone $approved)->where('transaction_type', 'IN')->sum('quantity'), 'Barang OUT hari ini' => (clone $approved)->where('transaction_type', 'OUT')->sum('quantity'), 'Stok menipis' => (clone $products)->whereBetween('stock', [1, 2])->count(), 'Stok habis' => (clone $products)->where('stock', 0)->count(), 'Transaksi pending' => StockTransaction::where('status', 'PENDING')->count(), 'Transaksi hari ini' => StockTransaction::where('created_at', '>=', $today)->where('created_at', '<', $tomorrow)->count()];

        return view('dashboard', ['stats' => $stats, 'alerts' => (clone $products)->with('position.rack.block.room')->where('stock', '<=', 2)->orderBy('stock')->orderBy('name')->limit(15)->get(), 'recent' => StockTransaction::where('status', 'APPROVED')->latest('approved_at')->limit(6)->get()]);
    }
}
