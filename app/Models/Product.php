<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id', 'stock', 'qr_token', 'active_position_id'];

    protected $hidden = ['active_position_id'];

    protected function casts(): array
    {
        return ['stock' => 'integer', 'active' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function position()
    {
        return $this->belongsTo(RackPosition::class, 'rack_position_id');
    }

    public function transactions()
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function approvedOut()
    {
        return $this->transactions()->where('transaction_type', 'OUT')->where('status', 'APPROVED');
    }

    public function getStockStatusAttribute(): string
    {
        return $this->stock === 0 ? 'STOK HABIS' : ($this->stock <= 2 ? 'STOK MENIPIS' : 'AMAN');
    }

    public function getMovingLabelAttribute(): string
    {
        return match ($this->moving_type) {
            'FAST' => 'Fast Moving', 'SLOW' => 'Slow Moving', default => 'Belum diklasifikasikan'
        };
    }

    public function scannerData(): array
    {
        return ['id' => $this->id, 'product_code' => $this->product_code, 'name' => $this->name, 'specification' => $this->specification, 'location' => $this->position->code, 'room' => $this->position->rack->block->room->name, 'stock' => $this->stock, 'unit' => $this->unit, 'moving_type' => $this->moving_type];
    }
}
