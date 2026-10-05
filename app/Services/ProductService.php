<?php

namespace App\Services;

use App\Exceptions\WarehouseException;
use App\Models\Category;
use App\Models\Product;
use App\Models\RackPosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    public static function masterLock(): void
    {
        DB::table('warehouse_mutexes')->where('id', 1)->lockForUpdate()->firstOrFail();
    }

    private function noPending(Product $p): void
    {
        if ($p->transactions()->where('status', 'PENDING')->exists()) {
            throw new WarehouseException('TRANSACTION_PENDING', 'Selesaikan transaksi pending sebelum mengubah barang.');
        }
    }

    public function save(array $data, ?int $id = null): Product
    {
        return DB::transaction(function () use ($data, $id) {
            self::masterLock();
            $p = $id ? Product::whereKey($id)->lockForUpdate()->firstOrFail() : new Product;
            if ($id) {
                $this->noPending($p);
            }
            if (Product::where('product_code', $data['product_code'])->when($id, fn ($q) => $q->where('id', '<>', $id))->exists()) {
                throw new WarehouseException('PRODUCT_CODE_EXISTS', "Kode Barang {$data['product_code']} sudah digunakan.");
            }
            $position = RackPosition::with('rack.block.room')->lockForUpdate()->findOrFail($data['rack_position_id']);
            if (! $position->available()) {
                throw new WarehouseException('LOCATION_INACTIVE', 'Lokasi tujuan tidak aktif.');
            }
            $occupant = Product::where('rack_position_id', $position->id)->where('active', true)->when($id, fn ($q) => $q->where('id', '<>', $id))->first();
            if ($occupant && (! $id || $p->active)) {
                throw new WarehouseException('POSITION_OCCUPIED', "Posisi {$position->code} sudah digunakan oleh {$occupant->product_code} — {$occupant->name} {$occupant->specification}.");
            }
            if (! Category::whereKey($data['category_id'])->where('active', true)->exists() && (! $id || $p->category_id != $data['category_id'])) {
                throw new WarehouseException('CATEGORY_INACTIVE', 'Kategori tidak aktif.');
            }
            if ($id && $p->rack_position_id != $position->id && empty($data['confirm_move'])) {
                throw new WarehouseException('MOVE_CONFIRMATION_REQUIRED', 'Konfirmasi perpindahan lokasi diperlukan.', [], 422);
            }
            // Strict allowlist; stock, token, active and generated columns never come from form data.
            $p->fill(collect($data)->only(['product_code', 'name', 'specification', 'category_id', 'unit', 'moving_type', 'rack_position_id', 'description', 'image'])->all());
            if (! $id) {
                $p->stock = 0;
                $p->qr_token = (string) Str::uuid();
                $p->active = true;
            }
            $p->save();

            return $p;
        }, 5);
    }

    public function setActive(int $id, bool $active): void
    {
        DB::transaction(function () use ($id, $active) {
            self::masterLock();
            $p = Product::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->noPending($p);
            if (! $active && $p->stock !== 0) {
                throw new WarehouseException('STOCK_NOT_ZERO', 'Kosongkan stok melalui OUT atau adjustment sebelum menonaktifkan barang.');
            }
            if ($active) {
                if (! $p->position->available()) {
                    throw new WarehouseException('LOCATION_INACTIVE', 'Aktifkan seluruh lokasi barang terlebih dahulu.');
                }
                if (Product::where('rack_position_id', $p->rack_position_id)->where('active', true)->where('id', '<>', $id)->exists()) {
                    throw new WarehouseException('POSITION_OCCUPIED', 'Posisi sudah digunakan barang lain.');
                }
            }
            $p->active = $active;
            $p->save();
        }, 5);
    }

    public function regenerate(int $id): void
    {
        DB::transaction(function () use ($id) {
            $p = Product::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->noPending($p);
            $p->qr_token = (string) Str::uuid();
            $p->save();
        }, 5);
    }
}
