<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['quantity' => 'required|integer|min:1|max:2147483647'];
    }

    public function messages(): array
    {
        return ['quantity.required' => 'Quantity wajib diisi.', 'quantity.integer' => 'Quantity harus bilangan bulat.', 'quantity.min' => 'Quantity harus lebih dari 0.'];
    }
}
