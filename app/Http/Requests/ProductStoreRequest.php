<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => 'nullable|unique:products,sku',
            'product_name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id,is_active,1',
            'brand' => 'required|string|max:50',
            'type' => 'nullable|string|max:50',
            'packaging' => 'required|string|max:50',
            'size' => 'required|string|max:50',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'holding_cost_per_day' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:1',
            'exp_warning_days' => 'required|integer|min:0',
        ];
    }
}
