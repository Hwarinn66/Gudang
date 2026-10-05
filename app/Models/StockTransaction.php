<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    protected $guarded = ['id', 'pending_product_id'];

    protected $hidden = ['pending_product_id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'stock_before' => 'integer', 'stock_after' => 'integer', 'scanned_at' => 'datetime', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function position()
    {
        return $this->belongsTo(RackPosition::class, 'rack_position_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
