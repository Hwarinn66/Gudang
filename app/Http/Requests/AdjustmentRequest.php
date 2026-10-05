<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['product_id' => 'required|integer|exists:products,id', 'physical_stock' => 'required|integer|min:0|max:2147483647', 'expected_stock' => 'required|integer|min:0|max:2147483647', 'notes' => 'required|string|min:3|max:2000', 'request_key' => 'required|uuid'];
    }
}
