<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustmentRequest;
use App\Models\Product;
use App\Services\StockTransactionService;

class AdjustmentController extends Controller
{
    public function index()
    {
        return view('adjustments.index', ['products' => Product::with('position.rack.block.room')->where('active', true)->orderBy('name')->get()]);
    }

    public function store(AdjustmentRequest $r, StockTransactionService $service)
    {
        $v = $r->validated();
        $service->adjust((int) $v['product_id'], (int) $v['physical_stock'], (int) $v['expected_stock'], $v['notes'], $v['request_key'], $r->user()->id);

        return redirect()->route('history', ['type' => 'adjustment'])->with('success', 'Adjustment berhasil disetujui.');
    }
}
