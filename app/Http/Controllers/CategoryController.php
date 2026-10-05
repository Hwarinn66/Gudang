<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('categories.index', ['categories' => Category::withCount('products')->orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100|unique:categories']);
        DB::transaction(function () use ($v) {
            ProductService::masterLock();
            Category::create($v);
        });

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function update(Request $r, Category $category)
    {
        $v = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($category->id)], 'active' => 'required|boolean']);
        DB::transaction(function () use ($category, $v) {
            ProductService::masterLock();
            $category->update($v);
        });

        return back()->with('success', 'Kategori diperbarui.');
    }
}
