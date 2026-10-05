<?php

namespace Tests\Support;

use App\Exceptions\WarehouseException;
use App\Models\Block;
use App\Models\Category;
use App\Models\Product;
use App\Models\Rack;
use App\Models\RackPosition;
use App\Models\Room;
use App\Models\User;
use App\Services\ProductService;
use App\Services\StockTransactionService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

abstract class WarehouseTestCase extends TestCase
{
    use DatabaseTruncation;

    protected StockTransactionService $stock;

    protected ProductService $products;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\DB::table('warehouse_mutexes')->insertOrIgnore(['id' => 1]);
        $this->stock = app(StockTransactionService::class);
        $this->products = app(ProductService::class);
        $room = Room::create(['name' => 'Ruangan 1', 'code' => 'R1']);
        $block = Block::create(['room_id' => $room->id, 'code' => 'A', 'name' => 'Blok A']);
        $rack = Rack::create(['block_id' => $block->id, 'rack_number' => 1]);
        foreach ([1, 2, 3, 10] as $n) {
            RackPosition::create(['rack_id' => $rack->id, 'position_number' => $n]);
        }
        Category::create(['name' => 'Peralatan']);
        $this->actingAs(User::create(['name' => 'Operator', 'username' => 'admin', 'email' => 'admin@example.test', 'password' => Hash::make('ValidPassword123!')]));
    }

    protected function product(string $code = 'A0001', string $name = 'Ember', int $position = 1, ?string $moving = 'FAST'): Product
    {
        return $this->products->save(['product_code' => $code, 'name' => $name, 'specification' => '1 KG', 'category_id' => Category::first()->id, 'unit' => 'PCS', 'rack_position_id' => RackPosition::where('position_number', $position)->first()->id, 'moving_type' => $moving]);
    }

    protected function putStock(Product $p, int $quantity): void
    {
        if ($quantity > 0) {
            $t = $this->stock->scan($p->qr_token, 'IN', null);
            $this->stock->approve($t->id, $quantity, null);
        }
    }

    protected function error(string $code, callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected '.$code);
        } catch (WarehouseException $e) {
            $this->assertSame($code, $e->errorCode);
        }
    }
}
