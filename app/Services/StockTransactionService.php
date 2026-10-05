<?php

namespace App\Services;

use App\Exceptions\WarehouseException;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Support\StockMath;
use Illuminate\Support\Facades\DB;

class StockTransactionService
{
    public function __construct(private TransactionNumberService $numbers) {}

    private function assertActive(Product $p): void
    {
        if (! $p->active) {
            throw new WarehouseException('PRODUCT_INACTIVE', 'Barang sudah tidak aktif.', ['product' => $p->scannerData()]);
        }
    }

    private function assertAvailable(Product $p): void
    {
        $pending = StockTransaction::where('product_id', $p->id)->where('status', 'PENDING')->first();
        if ($pending) {
            throw new WarehouseException('TRANSACTION_PENDING', 'Barang masih menunggu proses. Selesaikan transaksi sebelumnya terlebih dahulu.', ['product' => $p->scannerData(), 'transaction_number' => $pending->transaction_number, 'status' => 'PENDING']);
        }
    }

    private function snapshot(Product $p): array
    {
        return ['product_id' => $p->id, 'rack_position_id' => $p->rack_position_id, 'location_snapshot' => $p->position->code, 'room_snapshot' => $p->position->rack->block->room->name, 'product_code_snapshot' => $p->product_code, 'name_snapshot' => $p->name, 'specification_snapshot' => $p->specification, 'unit_snapshot' => $p->unit];
    }

    public function scan(string $token, string $mode, ?int $userId): StockTransaction
    {
        if (! in_array($mode, ['IN', 'OUT'], true)) {
            throw new WarehouseException('INVALID_MODE', 'Pilih mode IN atau OUT.', [], 422);
        }

        return DB::transaction(function () use ($token, $mode, $userId) {
            $p = Product::where('qr_token', $token)->lockForUpdate()->first();
            if (! $p) {
                throw new WarehouseException('QR_NOT_FOUND', 'QR TIDAK TERDAFTAR', [], 404);
            }
            $this->assertActive($p);
            $this->assertAvailable($p);
            if ($mode === 'OUT' && $p->stock === 0) {
                throw new WarehouseException('STOCK_EMPTY', 'STOK HABIS', ['product' => $p->scannerData()]);
            }

            return StockTransaction::create($this->snapshot($p) + ['transaction_number' => $this->numbers->next($mode), 'transaction_type' => $mode, 'status' => 'PENDING', 'scanned_at' => now(), 'created_by' => $userId]);
        }, 5);
    }

    // Always product FIRST, transaction SECOND. All transaction writers follow this order.
    private function lockedTransaction(int $id): array
    {
        $productId = StockTransaction::whereKey($id)->value('product_id');
        if (! $productId) {
            throw new WarehouseException('TRANSACTION_NOT_FOUND', 'Transaksi tidak ditemukan.', [], 404);
        }
        $p = Product::whereKey($productId)->lockForUpdate()->firstOrFail();
        $tx = StockTransaction::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($tx->status !== 'PENDING') {
            throw new WarehouseException('TRANSACTION_ALREADY_PROCESSED', 'Transaksi sudah diproses.', ['status' => $tx->status]);
        }

        return [$p, $tx];
    }

    public function quantity(int $id, int $quantity): StockTransaction
    {
        return DB::transaction(function () use ($id, $quantity) {
            [$p,$tx] = $this->lockedTransaction($id);
            StockMath::after($p->stock, $quantity, $tx->transaction_type);
            $tx->update(['quantity' => $quantity]);

            return $tx;
        }, 5);
    }

    public function approve(int $id, int $quantity, ?int $userId): StockTransaction
    {
        return DB::transaction(function () use ($id, $quantity, $userId) {
            [$p,$tx] = $this->lockedTransaction($id);
            $this->assertActive($p);
            $before = $p->stock;
            $after = StockMath::after($before, $quantity, $tx->transaction_type);
            $p->stock = $after;
            $p->save();
            // Failure here rolls back the product update too (covered by integration test).
            $tx->update(['quantity' => $quantity, 'stock_before' => $before, 'stock_after' => $after, 'status' => 'APPROVED', 'approved_at' => now(), 'processed_by' => $userId]);

            return $tx;
        }, 5);
    }

    public function cancel(int $id, ?int $userId): StockTransaction
    {
        return DB::transaction(function () use ($id, $userId) {
            [, $tx] = $this->lockedTransaction($id);
            $tx->update(['status' => 'CANCELLED', 'cancelled_at' => now(), 'processed_by' => $userId]);

            return $tx;
        }, 5);
    }

    public function adjust(int $productId, int $physicalStock, int $expectedStock, string $notes, string $requestKey, ?int $userId): StockTransaction
    {
        if ($physicalStock < 0 || $physicalStock > StockMath::MAX || trim($notes) === '') {
            throw new WarehouseException('INVALID_ADJUSTMENT', 'Stok fisik dan alasan wajib valid.', [], 422);
        }

        return DB::transaction(function () use ($productId, $physicalStock, $expectedStock, $notes, $requestKey, $userId) {
            $p = Product::whereKey($productId)->lockForUpdate()->firstOrFail();
            if (StockTransaction::where('request_key', $requestKey)->exists()) {
                throw new WarehouseException('TRANSACTION_ALREADY_PROCESSED', 'Permintaan adjustment ini sudah diproses.');
            }
            $this->assertActive($p);
            $this->assertAvailable($p);
            if ($p->stock !== $expectedStock) {
                throw new WarehouseException('STOCK_CHANGED', 'Stok telah berubah. Muat ulang dan periksa stok fisik sebelum menyetujui.', ['stock' => $p->stock]);
            }
            $before = $p->stock;
            if ($before === $physicalStock) {
                throw new WarehouseException('NO_STOCK_CHANGE', 'Stok fisik sama dengan stok sistem.', [], 422);
            }
            $tx = StockTransaction::create($this->snapshot($p) + ['transaction_number' => $this->numbers->next('ADJUSTMENT'), 'transaction_type' => 'ADJUSTMENT', 'quantity' => $physicalStock - $before, 'stock_before' => $before, 'stock_after' => $physicalStock, 'status' => 'APPROVED', 'approved_at' => now(), 'notes' => $notes, 'request_key' => $requestKey, 'created_by' => $userId, 'processed_by' => $userId]);
            $p->stock = $physicalStock;
            $p->save();

            return $tx;
        }, 5);
    }
}
