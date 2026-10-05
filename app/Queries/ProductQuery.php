<?php

namespace App\Queries;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductQuery
{
    public function build(Request $r)
    {
        $v = $r->validate(['q' => 'nullable|string|max:150', 'category_id' => 'nullable|integer', 'room_id' => 'nullable|integer', 'block_id' => 'nullable|integer', 'rack_id' => 'nullable|integer', 'moving_type' => 'nullable|in:FAST,SLOW', 'stock_status' => 'nullable|in:empty,low,safe', 'active' => 'nullable|in:1,0,all', 'sort' => 'nullable|in:name,stock,out,location,fast,slow', 'per_page' => 'nullable|in:25,50,100']);
        $q = Product::query()->select('products.*')->with(['category', 'position.rack.block.room'])->withSum('approvedOut as total_out', 'quantity')
            ->join('rack_positions as rp', 'rp.id', '=', 'products.rack_position_id')->join('racks as rk', 'rk.id', '=', 'rp.rack_id')->join('blocks as b', 'b.id', '=', 'rk.block_id')->join('rooms as rm', 'rm.id', '=', 'b.room_id');
        if (($v['active'] ?? '1') !== 'all') {
            $q->where('products.active', ($v['active'] ?? '1') === '1');
        }
        foreach (['category_id' => 'products.category_id', 'room_id' => 'rm.id', 'block_id' => 'b.id', 'rack_id' => 'rk.id', 'moving_type' => 'products.moving_type'] as $k => $col) {
            if (! empty($v[$k])) {
                $q->where($col, $v[$k]);
            }
        }
        if (! empty($v['q'])) {
            $search = '%'.$v['q'].'%';
            $q->where(function ($query) use ($search) {
                $query->where('products.product_code', 'like', $search)->orWhere('products.name', 'like', $search)->orWhere('products.specification', 'like', $search)->orWhere('rm.name', 'like', $search)
                    ->orWhereRaw("CONCAT(b.code,'.',IF(rk.rack_number<10,CONCAT('0',rk.rack_number),rk.rack_number),'.',IF(rp.position_number<10,CONCAT('0',rp.position_number),rp.position_number)) LIKE ?", [$search])
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $search));
            });
        }
        match ($v['stock_status'] ?? '') {
            'empty' => $q->where('stock', 0), 'low' => $q->where('stock', '<=', 2), 'safe' => $q->where('stock', '>', 2), default => null
        };
        switch ($v['sort'] ?? 'name') {
            case 'stock': $q->orderByDesc('stock');
                break;
            case 'out': $q->orderByRaw('COALESCE(total_out,0) DESC');
                break;
            case 'location': $q->orderBy('rm.name')->orderBy('rm.id')->orderBy('b.code')->orderBy('rk.rack_number')->orderBy('rp.position_number');
                break;
            case 'fast': $q->where('products.moving_type', 'FAST');
                break;
            case 'slow': $q->where('products.moving_type','SLOW');
                break;
        }

        return $q->orderBy('products.name')->orderBy('products.product_code');
    }
}
