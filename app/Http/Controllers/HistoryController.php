<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RackPosition;
use App\Queries\HistoryQuery;
use App\Support\Csv;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    private function type(string $type): string
    {
        abort_unless(in_array($type, ['in', 'out', 'adjustment']), 404);

        return $type === 'adjustment' ? 'ADJUSTMENT' : strtoupper($type);
    }

    public function index(Request $r, string $type, HistoryQuery $query)
    {
        $type = $this->type($type);

        return view('history.index', ['type' => $type, 'transactions' => $query->build($r, $type)->paginate((int) $r->input('per_page', 25))->withQueryString(), 'products' => Product::orderBy('name')->get(['id', 'name', 'product_code']), 'positions' => RackPosition::with('rack.block.room')->get()]);
    }

    public function export(Request $r, string $type, HistoryQuery $query)
    {
        $type = $this->type($type);
        $rows = (function () use ($query, $r, $type) {
            foreach ($query->build($r, $type)->lazy(200) as $t) {
                yield [$t->transaction_number, $t->created_at->format('Y-m-d'), $t->created_at->format('H:i:s'), $t->product_code_snapshot, $t->name_snapshot, $t->specification_snapshot, $t->room_snapshot, $t->location_snapshot, $t->quantity, $t->unit_snapshot, $t->stock_before, $t->stock_after, $t->scanned_at?->format('Y-m-d H:i:s'), $t->approved_at?->format('Y-m-d H:i:s'), $t->cancelled_at?->format('Y-m-d H:i:s'), $t->status, $t->notes];
            }
        })();

        return Csv::download('histori-'.$type.'-'.now()->format('Ymd-His').'.csv', ['Nomor', 'Tanggal', 'Jam', 'Kode', 'Nama', 'Spesifikasi', 'Ruangan', 'Lokasi', 'Quantity', 'Satuan', 'Stok Sebelum', 'Stok Setelah', 'Scanned At', 'Approved At', 'Cancelled At', 'Status', 'Alasan'], $rows);
    }
}
