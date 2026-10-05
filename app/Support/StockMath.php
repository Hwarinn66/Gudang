<?php

namespace App\Support;

use App\Exceptions\WarehouseException;

final class StockMath
{
    public const MAX = 2147483647;

    public static function after(int $before, int $quantity, string $type): int
    {
        if ($quantity < 1 || $quantity > self::MAX) {
            throw new WarehouseException('INVALID_QUANTITY', 'Quantity harus berupa bilangan bulat lebih dari 0.', [], 422);
        }
        if ($type === 'OUT' && $quantity > $before) {
            throw new WarehouseException('STOCK_NOT_ENOUGH', 'STOK TIDAK MENCUKUPI', ['stock' => $before, 'quantity' => $quantity]);
        }
        $after = $type === 'IN' ? $before + $quantity : $before - $quantity;
        if ($after < 0 || $after > self::MAX) {
            throw new WarehouseException('STOCK_LIMIT', 'Stok melampaui batas sistem.', [], 422);
        }

        return $after;
    }
}
