<?php

namespace Tests\Feature;

use App\Models\StockTransaction;
use Symfony\Component\Process\Process;
use Tests\Support\WarehouseTestCase;

class ConcurrencyTest extends WarehouseTestCase
{
    private function concurrent(array $operations, int $id): array
    {
        $barrier = sys_get_temp_dir().'/warehouse-'.bin2hex(random_bytes(6));
        $processes = [];
        foreach ($operations as $i => $op) {
            $p = new Process([PHP_BINARY, base_path('tests/Support/worker.php'), $op, (string) $id, $barrier, (string) $i], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'warehouse_stock_test']);
            $p->setTimeout(20);
            $p->start();
            $processes[] = $p;
        }
        $deadline = microtime(true) + 10;
        while (count(glob($barrier.'.ready.*')) < count($operations)) {
            if (microtime(true) > $deadline) {
                break;
            }usleep(10000);
        }file_put_contents($barrier, 'go');
        try {
            return array_map(function ($p) {
                $p->wait();
                $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput().$p->getOutput());
                $json = json_decode($p->getOutput(), true);
                $this->assertIsArray($json, $p->getOutput());

                return $json['result'];
            }, $processes);
        } finally {
            foreach (glob($barrier.'*') as $f) {
                unlink($f);
            }
        }
    }

    public function test_two_simultaneous_scans_create_one_pending(): void
    {
        $p = $this->product();
        $r = $this->concurrent(['scan', 'scan'], $p->id);
        sort($r);
        $this->assertSame(['OK', 'TRANSACTION_PENDING'], $r);
        $this->assertSame(1, StockTransaction::count());
        $this->assertSame(0, $p->fresh()->stock);
    }

    public function test_two_simultaneous_approvals_change_stock_once(): void
    {
        $p = $this->product();
        $t = $this->stock->scan($p->qr_token, 'IN', null);
        $r = $this->concurrent(['approve', 'approve'], $t->id);
        sort($r);
        $this->assertSame(['OK', 'TRANSACTION_ALREADY_PROCESSED'], $r);
        $this->assertSame(10, $p->fresh()->stock);
    }

    public function test_approve_versus_cancel_has_exactly_one_winner(): void
    {
        $p = $this->product();
        $t = $this->stock->scan($p->qr_token, 'IN', null);
        $r = $this->concurrent(['approve', 'cancel'], $t->id);
        sort($r);
        $this->assertSame(['OK', 'TRANSACTION_ALREADY_PROCESSED'], $r);
        $this->assertSame($t->fresh()->status === 'APPROVED' ? 10 : 0, $p->fresh()->stock);
    }
}
