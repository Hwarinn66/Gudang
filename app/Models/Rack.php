<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rack extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function positions()
    {
        return $this->hasMany(RackPosition::class);
    }

    public function getCodeAttribute(): string
    {
        return $this->block->code.'.'.str_pad((string) $this->rack_number, 2, '0', STR_PAD_LEFT);
    }
}
