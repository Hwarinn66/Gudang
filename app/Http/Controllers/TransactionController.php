<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuantityRequest;
use App\Http\Requests\ScanRequest;
use App\Models\StockTransaction;
use App\Services\StockTransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private StockTransactionService $service) {}

    public function index()
    {
        return view('pending');
    }

    public function pending()
    {
        $items = StockTransaction::with('product.position.rack.block.room')->where('status', 'PENDING')->orderBy('id')->get()->map(fn ($t) => ['id' => $t->id, 'transaction_number' => $t->transaction_number, 'transaction_type' => $t->transaction_type, 'quantity' => $t->quantity, 'scanned_at' => $t->scanned_at->format('d/m/Y H:i:s'), 'product' => $t->product->scannerData()]);

        return response()->json(['success' => true, 'message' => 'Data pending.', 'data' => $items, 'server_time' => now()->toIso8601String()]);
    }

    public function scan(ScanRequest $r)
    {
        $t = $this->service->scan($r->validated('qr_token'), $r->validated('mode'), $r->user()->id);

        return response()->json(['success' => true, 'message' => 'QR BERHASIL DIBACA', 'data' => ['transaction_number' => $t->transaction_number, 'status' => $t->status, 'mode' => $t->transaction_type, 'product' => $t->product->scannerData()]], 201);
    }

    public function quantity(QuantityRequest $r, StockTransaction $transaction)
    {
        $t = $this->service->quantity($transaction->id, (int) $r->validated('quantity'));

        return response()->json(['success' => true, 'message' => 'Quantity tersimpan.', 'data' => $t]);
    }

    public function approve(QuantityRequest $r, StockTransaction $transaction)
    {
        $t = $this->service->approve($transaction->id, (int) $r->validated('quantity'), $r->user()->id);
        $verb = $t->transaction_type === 'IN' ? 'masuk +' : 'keluar -';

        return response()->json(['success' => true, 'message' => "Barang berhasil {$verb}{$t->quantity} {$t->unit_snapshot}.", 'data' => $t]);
    }

    public function cancel(Request $r, StockTransaction $transaction)
    {
        $t = $this->service->cancel($transaction->id, $r->user()->id);

        return response()->json(['success' => true, 'message' => 'Transaksi dibatalkan. Stok tidak berubah.', 'data' => $t]);
    }
}
