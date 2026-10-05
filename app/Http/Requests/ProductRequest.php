<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('product_code'))) {
            $this->merge(['product_code' => strtoupper(trim($this->input('product_code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'product_code' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('products', 'product_code')->ignore($this->route('product')?->id)],
            'name' => 'required|string|max:150', 'specification' => 'nullable|string|max:150', 'category_id' => 'required|integer|exists:categories,id', 'unit' => 'required|string|max:20',
            'moving_type' => ['nullable', Rule::in(['FAST', 'SLOW'])], 'rack_position_id' => 'required|integer|exists:rack_positions,id', 'description' => 'nullable|string|max:5000',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096', 'confirm_move' => 'nullable|boolean',
            'stock' => 'prohibited', 'qr_token' => 'prohibited', 'active' => 'prohibited',
        ];
    }

    public function messages(): array
    {
        $code = is_string($this->input('product_code')) ? $this->input('product_code') : '';
        return ['product_code.unique' => "Kode Barang {$code} sudah digunakan.", 'photo.max' => 'Ukuran foto maksimal 4 MB.'];
    }
}
