<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RackPosition extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function getCodeAttribute(): string
    {
        return $this->rack->code.'.'.str_pad((string) $this->position_number, 2, '0', STR_PAD_LEFT);
    }

    public function available(): bool
    {
        return $this->active && $this->rack->active && $this->rack->block->active && $this->rack->block->room->active;
    }
}
