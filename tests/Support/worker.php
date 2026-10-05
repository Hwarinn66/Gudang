<?php

use App\Exceptions\WarehouseException;
use App\Models\Product;
use App\Services\StockTransactionService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'warehouse_stock_test') {
    exit(3);
}
[$script,$operation,$id,$barrier,$worker] = $argv;
file_put_contents($barrier.'.ready.'.$worker, '1');
$until = microtime(true) + 10;
while (! file_exists($barrier)) {
    if (microtime(true) > $until) {
        exit(4);
    }usleep(10000);
}
try {
    $service = app(StockTransactionService::class);
    if ($operation === 'scan') {
        $p = Product::findOrFail($id);
        $service->scan($p->qr_token, 'IN', null);
    } elseif ($operation === 'approve') {
        $service->approve((int) $id, 10, null);
    } elseif ($operation === 'cancel') {
        $service->cancel((int) $id, null);
    }
    echo json_encode(['result' => 'OK']);
} catch (WarehouseException $e) {
    echo json_encode(['result' => $e->errorCode]);
}
