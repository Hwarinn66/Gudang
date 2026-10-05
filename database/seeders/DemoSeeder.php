<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Category;
use App\Models\Product;
use App\Models\Rack;
use App\Models\RackPosition;
use App\Models\Room;
use App\Services\ProductService;
use App\Services\StockTransactionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            ProductService::masterLock();
            foreach (['Elektronik', 'Peralatan', 'Kabel', 'Plastik', 'ATK', 'Sparepart'] as $name) {
                Category::firstOrCreate(['name' => $name]);
            }
            $room = Room::firstOrCreate(['code' => 'R1'], ['name' => 'Ruangan 1']);
            foreach (['A' => [1 => [1, 2, 3], 2 => [1, 2]], 'B' => [1 => [1]]] as $code => $racks) {
                $block = Block::firstOrCreate(['room_id' => $room->id, 'code' => $code], ['name' => 'Blok '.$code]);
                foreach ($racks as $number => $positions) {
                    $rack = Rack::firstOrCreate(['block_id' => $block->id, 'rack_number' => $number], ['name' => 'Rak '.str_pad($number, 2, '0', STR_PAD_LEFT)]);
                    foreach ($positions as $n) {
                        RackPosition::firstOrCreate(['rack_id' => $rack->id, 'position_number' => $n]);
                    }
                }
            }
            $rack = Rack::whereHas('block', fn ($q) => $q->where('room_id', $room->id)->where('code', 'A'))->where('rack_number', 1)->firstOrFail();
            foreach ([['A0001', '1 KG', 1, 'FAST', 25], ['A0002', '1.2 KG', 2, 'FAST', 2], ['A0003', '2 KG', 3, 'SLOW', 0]] as [$code,$spec,$pos,$moving,$stock]) {
                if (Product::where('product_code', $code)->exists()) {
                    continue;
                }
                $p = app(ProductService::class)->save(['product_code' => $code, 'name' => 'Ember', 'specification' => $spec, 'category_id' => Category::where('name', 'Plastik')->value('id'), 'unit' => 'PCS', 'moving_type' => $moving, 'rack_position_id' => $rack->positions()->where('position_number', $pos)->value('id'), 'description' => 'Contoh data gudang']);
                if ($stock > 0) {
                    $service = app(StockTransactionService::class);
                    $t = $service->scan($p->qr_token, 'IN', null);
                    $service->approve($t->id, $stock, null);
                    $t->refresh()->update(['notes' => 'Stok demo melalui transaksi IN (bukan input stok awal).']);
                }
            }
        }, 5);
    }
}
