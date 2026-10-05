<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Product;
use App\Models\Rack;
use App\Models\RackPosition;
use App\Models\Room;
use App\Models\StockTransaction;
use App\Queries\ProductQuery;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\WarehouseTestCase;

class WarehouseTest extends WarehouseTestCase
{
    public function test_new_product_starts_zero_has_unique_qr_and_printable_label(): void
    {
        $this->post('/products', ['product_code' => 'a0001', 'name' => 'Ember', 'specification' => '1 KG', 'category_id' => 1, 'unit' => 'PCS', 'rack_position_id' => 1, 'moving_type' => 'FAST'])->assertRedirect();
        $p = Product::first();
        $this->assertSame(0, $p->stock);
        $this->assertSame('A0001', $p->product_code);
        $this->assertTrue(Str::isUuid($p->qr_token));
        $this->get('/products/'.$p->id.'/qr')->assertOk()->assertSee('A0001');
        $this->get('/products/'.$p->id.'/qr.svg?download=1')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);
    }

    public function test_code_and_active_position_duplicates_are_rejected(): void
    {
        $this->product();
        $this->error('POSITION_OCCUPIED', fn () => $this->product('A0002'));
        $this->error('PRODUCT_CODE_EXISTS', fn () => $this->product('A0001', 'Other', 2));
        $this->assertSame(1, Product::count());
    }

    public function test_in_out_preview_and_approval_flow(): void
    {
        $p = $this->product();
        $this->postJson('/api/scan', ['qr_token' => $p->qr_token, 'mode' => 'IN'])->assertCreated()->assertJsonPath('data.status', 'PENDING');
        $this->assertSame(0, $p->fresh()->stock);
        $t = StockTransaction::first();
        $this->patchJson('/api/transactions/'.$t->id.'/quantity', ['quantity' => 10])->assertOk();
        $this->assertSame(0, $p->fresh()->stock);
        $this->postJson('/api/transactions/'.$t->id.'/approve', ['quantity' => 10])->assertOk()->assertJsonPath('data.stock_after', 10);
        $out = $this->stock->scan($p->qr_token, 'OUT', null);
        $this->stock->approve($out->id, 3, null);
        $this->assertSame(7, $p->fresh()->stock);
        $this->assertSame(10, $out->fresh()->stock_before);
        $this->assertNotNull($out->fresh()->approved_at);
    }

    public function test_double_scan_both_modes_blocked_until_cancel_or_approve(): void
    {
        $p = $this->product();
        $t = $this->stock->scan($p->qr_token, 'IN', null);
        foreach (['IN', 'OUT'] as $mode) {
            $this->error('TRANSACTION_PENDING', fn () => $this->stock->scan($p->qr_token, $mode, null));
        }
        $this->stock->cancel($t->id, null);
        $this->assertSame(0, $p->fresh()->stock);
        $this->assertNotNull($t->fresh()->cancelled_at);
        $next = $this->stock->scan($p->qr_token, 'IN', null);
        $this->stock->approve($next->id, 1, null);
        $this->assertSame('PENDING', $this->stock->scan($p->qr_token, 'OUT', null)->status);
        $this->error('TRANSACTION_ALREADY_PROCESSED', fn () => $this->stock->approve($t->id, 1, null));
        $this->error('TRANSACTION_ALREADY_PROCESSED', fn () => $this->stock->approve($next->id, 1, null));
        $this->error('TRANSACTION_ALREADY_PROCESSED', fn () => $this->stock->cancel($next->id, null));
    }

    public function test_out_over_stock_rejected_and_empty_stock_never_pending(): void
    {
        $p = $this->product();
        $this->postJson('/api/scan', ['qr_token' => $p->qr_token, 'mode' => 'OUT'])->assertStatus(409)->assertJsonPath('code', 'STOCK_EMPTY');
        $this->assertSame(0, StockTransaction::count());
        $this->putStock($p, 2);
        $t = $this->stock->scan($p->qr_token, 'OUT', null);
        $this->postJson('/api/transactions/'.$t->id.'/approve', ['quantity' => 5])->assertStatus(409)->assertJsonPath('code', 'STOCK_NOT_ENOUGH');
        $this->assertSame(2, $p->fresh()->stock);
        $this->assertSame('PENDING', $t->fresh()->status);
    }

    public static function invalidQuantities(): array
    {
        return [[0], [-2], ['abc'], [''], [null], [1.5], [2147483648]];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantity_never_changes_stock($qty): void
    {
        $p = $this->product();
        $t = $this->stock->scan($p->qr_token, 'IN', null);
        $this->postJson('/api/transactions/'.$t->id.'/approve', ['quantity' => $qty])->assertStatus(422)->assertJsonPath('code', 'INVALID_QUANTITY');
        $this->assertSame(0, $p->fresh()->stock);
    }

    public function test_low_stock_boundary_and_manual_moving_unchanged(): void
    {
        $p = $this->product(moving: 'SLOW');
        $this->putStock($p, 5);
        $t = $this->stock->scan($p->qr_token, 'OUT', null);
        $this->stock->approve($t->id, 3, null);
        $this->assertSame('STOK MENIPIS', $p->fresh()->stock_status);
        $this->assertSame('SLOW', $p->fresh()->moving_type);
    }

    public function test_approval_rolls_back_if_transaction_update_fails(): void
    {
        $p = $this->product();
        $t = $this->stock->scan($p->qr_token, 'IN', null);
        DB::unprepared("CREATE TRIGGER fail_approval BEFORE UPDATE ON stock_transactions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected test failure'");
        try {
            $this->stock->approve($t->id, 10, null);
            $this->fail('Should roll back');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Injected test failure', $e->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_approval');
        }
        $this->assertSame(0, $p->fresh()->stock);
        $this->assertSame('PENDING', $t->fresh()->status);
        $this->assertNull($t->fresh()->stock_before);
    }

    public function test_approval_rolls_back_if_stock_update_fails(): void
    {
        $p = $this->product();
        $t = $this->stock->scan($p->qr_token, 'IN', null);
        DB::unprepared("CREATE TRIGGER fail_product BEFORE UPDATE ON products FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected product failure'");
        try {
            $this->stock->approve($t->id, 10, null);
            $this->fail('Should roll back');
        } catch (QueryException $e) {
        } finally {
            DB::unprepared('DROP TRIGGER fail_product');
        }
        $this->assertSame(0, $p->fresh()->stock);
        $this->assertSame('PENDING', $t->fresh()->status);
    }

    public function test_adjustment_records_signed_delta_and_rejects_stale_duplicate_pending(): void
    {
        $p = $this->product();
        $this->putStock($p, 10);
        $key = (string) Str::uuid();
        $t = $this->stock->adjust($p->id, 8, 10, '2 unit rusak', $key, null);
        $this->assertSame(-2, $t->quantity);
        $this->assertSame(8, $p->fresh()->stock);
        $this->assertSame('ADJUSTMENT', $t->transaction_type);
        $this->error('TRANSACTION_ALREADY_PROCESSED', fn () => $this->stock->adjust($p->id, 8, 10, '2 unit rusak', $key, null));
        $this->error('STOCK_CHANGED', fn () => $this->stock->adjust($p->id, 7, 10, 'Stale data', (string) Str::uuid(), null));
        $this->stock->scan($p->qr_token, 'OUT', null);
        $this->error('TRANSACTION_PENDING', fn () => $this->stock->adjust($p->id, 0, 8, 'Rusak', (string) Str::uuid(), null));
    }

    public function test_default_fast_slow_sort_and_combined_filters(): void
    {
        $this->product('A01', 'Zebra', 1, 'FAST');
        $this->product('A02', 'Ember', 2, 'SLOW');
        $this->product('A03', 'Adaptor', 3, 'FAST');
        $this->product('A04', 'Baut', 10, 'SLOW');
        $query = app(ProductQuery::class);
        $names = fn ($params) => $query->build(Request::create('/products', 'GET', $params))->pluck('products.name')->all();
        $this->assertSame(['Adaptor', 'Baut', 'Ember', 'Zebra'], $names([]));
        $this->assertSame(['Adaptor', 'Zebra'], $names(['sort' => 'fast']));
        $this->assertSame(['Baut', 'Ember'], $names(['sort' => 'slow']));
        $this->assertSame(['Ember'], $names(['q' => 'Em', 'moving_type' => 'SLOW', 'category_id' => 1, 'sort' => 'name']));
        $this->assertSame(['Zebra'], $names(['q' => 'A.01.01']));
        $this->get('/products?per_page=25&q=Em')->assertOk()->assertSee('Ember')->assertDontSee('Zebra');
    }

    public function test_total_out_only_approved_and_stock_sort(): void
    {
        $a = $this->product('A0001', 'Ember', 1);
        $b = $this->product('A0002', 'Baut', 2);
        $c = $this->product('A0003', 'Adaptor', 3);
        foreach ([[$a, 500], [$b, 750], [$c, 100]] as [$p,$out]) {
            $this->putStock($p, 1000);
            $t = $this->stock->scan($p->qr_token, 'OUT', null);
            $this->stock->approve($t->id, $out, null);
        }
        $t = $this->stock->scan($a->qr_token, 'OUT', null);
        $this->stock->quantity($t->id, 300);
        $this->stock->cancel($t->id, null);
        $this->stock->scan($a->qr_token, 'OUT', null);
        $q = app(ProductQuery::class);
        $rows = $q->build(Request::create('/', 'GET', ['sort' => 'out']))->get();
        $this->assertSame(['A0002', 'A0001', 'A0003'], $rows->pluck('product_code')->all());
        $this->assertSame([750, 500, 100], $rows->map(fn ($p) => (int) $p->total_out)->all());
        $this->assertSame(['A0003', 'A0001', 'A0002'], $q->build(Request::create('/', 'GET', ['sort' => 'stock']))->pluck('products.product_code')->all());
    }

    public function test_numeric_location_sort_and_history_snapshot_survive_move(): void
    {
        $a = $this->product('A01', 'Z', 1);
        $this->product('A02', 'Y', 2);
        $this->product('A03', 'X', 10);
        $b = Block::first();
        $rack = Rack::create(['block_id' => $b->id, 'rack_number' => 2]);
        $dest = RackPosition::create(['rack_id' => $rack->id, 'position_number' => 1]);
        $this->putStock($a, 4);
        $tx = $a->transactions()->first();
        $v = $a->only(['product_code', 'name', 'specification', 'category_id', 'unit', 'moving_type']);
        $v['rack_position_id'] = $dest->id;
        $v['confirm_move'] = true;
        $v['product_code'] = 'NEW01';
        $this->products->save($v, $a->id);
        $this->assertSame('A.01.01', $tx->fresh()->location_snapshot);
        $this->assertSame('A01', $tx->fresh()->product_code_snapshot);
        $ordered = app(ProductQuery::class)->build(Request::create('/', 'GET', ['sort' => 'location']))->get()->map(fn ($p) => $p->position->code)->all();
        $this->assertSame(['A.01.02', 'A.01.10', 'A.02.01'], $ordered);
    }

    public function test_inactive_product_releases_position_but_scan_rejected(): void
    {
        $p = $this->product();
        $this->products->setActive($p->id, false);
        $this->product('A0002');
        $this->error('PRODUCT_INACTIVE', fn () => $this->stock->scan($p->qr_token, 'IN', null));
        $this->error('POSITION_OCCUPIED', fn () => $this->products->setActive($p->id, true));
    }

    public function test_stock_and_pending_prevent_unsafe_master_edits(): void
    {
        $p = $this->product();
        $this->putStock($p, 2);
        $this->error('STOCK_NOT_ZERO', fn () => $this->products->setActive($p->id, false));
        $this->stock->scan($p->qr_token, 'IN', null);
        $this->error('TRANSACTION_PENDING', fn () => $this->products->regenerate($p->id));
        $this->post('/locations/rooms/1/active', ['active' => 0])->assertSessionHasErrors();
        $this->assertTrue(Room::first()->active);
        $this->put('/products/'.$p->id, ['stock' => 999])->assertSessionHasErrors('stock');
        $this->assertSame(2, $p->fresh()->stock);
    }

    public function test_qr_regeneration_invalidates_old_token(): void
    {
        $p = $this->product();
        $old = $p->qr_token;
        $this->products->regenerate($p->id);
        $this->error('QR_NOT_FOUND', fn () => $this->stock->scan($old, 'IN', null));
        $this->assertSame('PENDING', $this->stock->scan($p->fresh()->qr_token, 'IN', null)->status);
    }

    public function test_database_constraints_protect_direct_writes(): void
    {
        $p = $this->product();
        $failures = 0;
        foreach ([fn () => DB::table('products')->where('id', $p->id)->update(['stock' => -1]), fn () => DB::table('products')->insert(array_merge($p->only(['product_code', 'name', 'category_id', 'unit', 'rack_position_id', 'qr_token']), ['product_code' => 'A0002', 'qr_token' => (string) Str::uuid()]))] as $write) {
            try {
                $write();
            } catch (QueryException $e) {
                $failures++;
            }
        }
        $this->assertSame(2, $failures);
        $t = $this->stock->scan($p->qr_token, 'IN', null);
        $attributes = $t->getAttributes();
        unset($attributes['id'],$attributes['pending_product_id']);
        $attributes['transaction_number'] = 'IN-DUPLICATE';
        try {
            DB::table('stock_transactions')->insert($attributes);
            $this->fail('Unique pending constraint should reject');
        } catch (QueryException $e) {
            $this->assertSame(1, StockTransaction::where('status', 'PENDING')->count());
        }
    }

    public function test_login_and_private_scan_endpoints(): void
    {
        auth()->logout();
        $this->get('/scanner')->assertRedirect('/login');
        $this->postJson('/api/scan', ['qr_token' => 'x', 'mode' => 'IN'])->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->post('/login', ['login' => 'admin', 'password' => 'bad'])->assertSessionHasErrors();
        $this->post('/login', ['login' => 'admin', 'password' => 'ValidPassword123!'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_unknown_qr_pending_endpoint_views_and_csv(): void
    {
        $p = $this->product();
        $this->postJson('/api/scan', ['qr_token' => 'not-registered', 'mode' => 'IN'])->assertNotFound()->assertJsonPath('code', 'QR_NOT_FOUND');
        $t = $this->stock->scan($p->qr_token, 'IN', null);
        $this->getJson('/api/pending-transactions')->assertOk()->assertJsonPath('data.0.product.product_code', 'A0001');
        foreach (['/dashboard', '/pending-transactions', '/products', '/products/create', '/products/'.$p->id, '/products/'.$p->id.'/edit', '/history/in', '/history/out', '/history/adjustment', '/stock-adjustments', '/locations', '/categories', '/settings', '/scanner'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/products/export?q=missing')->assertOk()->assertDontSee('A0001');
        $this->get('/history/in/export')->assertOk();
    }

    public function test_history_allows_one_sided_dates_and_export_matches_filters(): void
    {
        $p = $this->product(name: '@SUM(1+1)');
        $this->putStock($p, 10);
        $this->get('/history/in?to=2099-12-31')->assertOk()->assertSee('A0001');
        $this->get('/history/in?from=2000-01-01')->assertOk()->assertSee('A0001');
        $this->get('/history/in?from=2099-12-31&to=2000-01-01')->assertSessionHasErrors('to');
        $export = $this->get('/history/in/export?status=CANCELLED');
        $export->assertOk();
        $this->assertStringNotContainsString('A0001', $export->streamedContent());
        $csv = $this->get('/products/export?q=A0001')->streamedContent();
        $this->assertStringContainsString("'@SUM(1+1)", $csv);
    }

    public function test_demo_seeder_is_idempotent_and_stocks_have_history(): void
    {
        // This test uses its own empty warehouse rather than the common fixture.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['rack_positions', 'racks', 'blocks', 'rooms', 'categories'] as $table) {
            DB::table($table)->truncate();
        }DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->seed();
        $this->seed();
        $this->assertSame(3,Product::count());
        $this->assertSame(2,StockTransaction::count());
        $this->assertSame([25, 2, 0],Product::orderBy('product_code')->pluck('stock')->all());
        $this->artisan('warehouse:audit')->assertExitCode(0);
    }
}
