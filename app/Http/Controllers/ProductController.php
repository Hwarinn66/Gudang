<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Block;
use App\Models\Category;
use App\Models\Product;
use App\Models\Rack;
use App\Models\RackPosition;
use App\Models\Room;
use App\Queries\ProductQuery;
use App\Services\ProductService;
use App\Services\QrService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    private function options(): array
    {
        return ['categories' => Category::orderBy('name')->get(), 'rooms' => Room::orderBy('name')->get(), 'blocks' => Block::with('room')->orderBy('code')->get(), 'racks' => Rack::with('block.room')->orderBy('rack_number')->get()];
    }

    public function index(Request $r, ProductQuery $query)
    {
        return view('products.index', $this->options() + ['products' => $query->build($r)->paginate((int) $r->input('per_page', 25))->withQueryString()]);
    }

    public function create()
    {
        return $this->form(new Product);
    }

    public function edit(Product $product)
    {
        return $this->form($product);
    }

    private function form(Product $p)
    {
        $positions = RackPosition::with(['rack.block.room', 'products' => fn ($q) => $q->where('active', true)])->get()->map(fn ($x) => ['id' => $x->id, 'rack_id' => $x->rack_id, 'block_id' => $x->rack->block_id, 'room_id' => $x->rack->block->room_id, 'code' => $x->code, 'active' => $x->available(), 'occupant_id' => $x->products->first()?->id, 'occupant' => $x->products->first()?->product_code]);

        return view('products.form', $this->options() + ['product' => $p, 'positions' => $positions]);
    }

    private function persist(ProductRequest $r, ?Product $product = null)
    {
        $data = $r->validated();
        $newImage = null;
        if ($r->hasFile('photo')) {
            $newImage = $r->file('photo')->store('products', 'local');
            if (! $newImage) { throw new \App\Exceptions\WarehouseException('IMAGE_STORE_FAILED', 'Foto gagal disimpan. Periksa izin storage.', [], 500); }
            $data['image'] = $newImage;
        }
        try {
            $saved = $this->service->save($data, $product?->id);
        } catch (\Throwable $e) {
            if ($newImage) {
                Storage::disk('local')->delete($newImage);
            } throw $e;
        }
        if ($newImage && $product?->image) {
            Storage::disk('local')->delete($product->image);
        }

        return redirect()->route('products.show', $saved)->with('success', 'Barang berhasil disimpan.');
    }

    public function store(ProductRequest $r)
    {
        return $this->persist($r);
    }

    public function update(ProductRequest $r, Product $product)
    {
        return $this->persist($r, $product);
    }

    public function show(Product $product)
    {
        $product->load('category', 'position.rack.block.room')->loadSum('approvedOut as total_out', 'quantity');

        return view('products.show', ['product' => $product, 'in' => $product->transactions()->where('transaction_type', 'IN')->latest('id')->limit(10)->get(), 'out' => $product->transactions()->where('transaction_type', 'OUT')->latest('id')->limit(10)->get()]);
    }

    public function active(Request $r, Product $product)
    {
        $v = $r->validate(['active' => 'required|boolean']);
        $this->service->setActive($product->id, (bool) $v['active']);

        return back()->with('success', 'Status barang diperbarui.');
    }

    public function qr(Product $product)
    {
        return view('products.qr', compact('product'));
    }

    public function image(Product $product)
    {
        abort_unless($product->image && Storage::disk('local')->exists($product->image), 404);

        return response()->file(Storage::disk('local')->path($product->image));
    }

    public function qrImage(Request $r, Product $product, QrService $qr)
    {
        return response($qr->svg($product), 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => ($r->boolean('download') ? 'attachment' : 'inline').'; filename="QR-'.$product->product_code.'.svg"']);
    }

    public function regenerate(Product $product)
    {
        $this->service->regenerate($product->id);

        return back()->with('success', 'QR baru dibuat. Cetak ulang label; QR lama tidak berlaku.');
    }

    public function search(Request $r, ProductQuery $query)
    {
        return response()->json(['success' => true, 'message' => 'Barang ditemukan.', 'data' => $query->build($r)->limit(30)->get()->map(fn ($p) => $p->scannerData())]);
    }

    public function export(Request $r, ProductQuery $query)
    {
        $rows = (function () use ($r, $query) {
            foreach ($query->build($r)->lazy(200) as $p) {
                yield [$p->product_code, $p->name, $p->specification, $p->category->name, $p->position->rack->block->room->name, $p->position->code, $p->stock, $p->unit, $p->moving_type, (int) $p->total_out, $p->stock_status, $p->active ? 'AKTIF' : 'NONAKTIF'];
            }
        })();

        return Csv::download('barang-'.now()->format('Ymd-His').'.csv', ['Kode', 'Nama', 'Spesifikasi', 'Kategori', 'Ruangan', 'Lokasi', 'Stok', 'Satuan', 'Moving Type', 'Total OUT', 'Status Stok', 'Status Barang'], $rows);
    }
}
