<?php

namespace App\Http\Controllers;

use App\Exceptions\WarehouseException;
use App\Models\Block;
use App\Models\Product;
use App\Models\Rack;
use App\Models\RackPosition;
use App\Models\Room;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function index()
    {
        return view('locations.index', ['rooms' => Room::with('blocks.racks.positions.products')->orderBy('name')->get()]);
    }

    public function store(Request $r, string $kind)
    {
        abort_unless(in_array($kind, ['rooms', 'blocks', 'racks', 'positions']), 404);
        $v = match ($kind) {
            'rooms' => $r->validate(['name' => 'required|string|max:100|unique:rooms', 'code' => 'required|alpha_dash|max:30|unique:rooms']),
            'blocks' => $r->validate(['room_id' => 'required|integer|exists:rooms,id', 'code' => ['required', 'regex:/^[A-Z]{1,10}$/', Rule::unique('blocks')->where('room_id', $r->input('room_id'))], 'name' => 'required|string|max:100']),
            'racks' => $r->validate(['block_id' => 'required|integer|exists:blocks,id', 'rack_number' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('racks')->where('block_id', $r->input('block_id'))], 'name' => 'nullable|string|max:100']),
            'positions' => $r->validate(['rack_id' => 'required|integer|exists:racks,id', 'position_number' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('rack_positions')->where('rack_id', $r->input('rack_id'))]]),
        };
        DB::transaction(function () use ($kind, $v) {
            ProductService::masterLock();
            $parent = match ($kind) {
                'blocks' => Room::findOrFail($v['room_id']),'racks' => Block::findOrFail($v['block_id']),'positions' => Rack::findOrFail($v['rack_id']),default => null
            };
            if ($parent && ! $parent->active) {
                throw new WarehouseException('LOCATION_INACTIVE', 'Aktifkan lokasi induk terlebih dahulu.');
            }
            $model = match ($kind) {
                'rooms' => Room::class,'blocks' => Block::class,'racks' => Rack::class,'positions' => RackPosition::class
            };
            $model::create($v);
        }, 5);

        return back()->with('success', 'Lokasi berhasil ditambahkan.');
    }

    public function active(Request $r, string $kind, int $id)
    {
        $v = $r->validate(['active' => 'required|boolean']);
        $model = match ($kind) {
            'rooms' => Room::class,'blocks' => Block::class,'racks' => Rack::class,'positions' => RackPosition::class,default => abort(404)
        };
        DB::transaction(function () use ($model, $kind, $id, $v) {
            ProductService::masterLock();
            $location = $model::lockForUpdate()->findOrFail($id);
            $positions = RackPosition::query();
            match ($kind) {
                'rooms' => $positions->whereHas('rack.block', fn ($q) => $q->where('room_id', $id)),'blocks' => $positions->whereHas('rack', fn ($q) => $q->where('block_id', $id)),'racks' => $positions->where('rack_id', $id),'positions' => $positions->whereKey($id)
            };
            if (! $v['active'] && Product::where('active', true)->whereIn('rack_position_id', $positions->select('id'))->exists()) {
                throw new WarehouseException('LOCATION_IN_USE', 'Lokasi masih digunakan barang aktif. Pindahkan atau nonaktifkan barang terlebih dahulu.');
            }
            if ($v['active']) {
                $parent = match ($kind) {
                    'blocks' => $location->room,'racks' => $location->block,'positions' => $location->rack,default => null
                };
                if ($parent && ! $parent->active) {
                    throw new WarehouseException('LOCATION_INACTIVE', 'Aktifkan lokasi induk terlebih dahulu.');
                }
            }
            $location->update(['active' => (bool) $v['active']]);
        }, 5);

        return back()->with('success','Status lokasi diperbarui.');
    }
}
