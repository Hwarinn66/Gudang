<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function racks()
    {
        return $this->hasMany(Rack::class);
    }
}
