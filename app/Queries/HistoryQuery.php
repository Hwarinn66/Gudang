<?php

namespace App\Queries;

use App\Models\StockTransaction;
use Illuminate\Http\Request;

class HistoryQuery
{
    public function build(Request $r, string $type)
    {
        $v = $r->validate(['q' => 'nullable|string|max:150', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($r->filled('from') ? '|after_or_equal:from' : ''), 'product_id' => 'nullable|integer', 'rack_position_id' => 'nullable|integer', 'status' => 'nullable|in:PENDING,APPROVED,CANCELLED', 'sort' => 'nullable|in:newest,oldest,quantity', 'per_page' => 'nullable|in:25,50,100']);
        $q = StockTransaction::where('transaction_type', $type);
        if (! empty($v['q'])) {
            $q->where(function ($q) use ($v) {
                foreach (['transaction_number', 'product_code_snapshot', 'name_snapshot', 'specification_snapshot', 'location_snapshot', 'room_snapshot', 'notes'] as $c) {
                    $q->orWhere($c, 'like', '%'.$v['q'].'%');
                }
            });
        }
        foreach (['product_id', 'rack_position_id', 'status'] as $k) {
            if (! empty($v[$k])) {
                $q->where($k, $v[$k]);
            }
        }
        // Date filter is transaction creation/scan day; approval has its own visible timestamp.
        if (! empty($v['from'])) {
            $q->where('created_at', '>=', $v['from'].' 00:00:00');
        }
        if (! empty($v['to'])) {
            $q->where('created_at', '<=', $v['to'].' 23:59:59');
        }

        return match ($v['sort'] ?? 'newest') {
            'oldest' => $q->orderBy('created_at')->orderBy('id'),'quantity' => $q->orderByDesc('quantity')->orderByDesc('id'),default => $q->orderByDesc('created_at')->orderByDesc('id')
        };
    }
}
