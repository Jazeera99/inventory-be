<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StockOrderUpdateRequest extends FormRequest
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
            'supplier_id' => 'nullable|exists:suppliers,id',
            'customer_id' => 'nullable|exists:customers,id',
            'status' => 'nullable|in:DRAFT,PENDING,PARTIAL,COMPLETED,CANCELLED',
            'order_date' => 'sometimes|required|date',
            'expected_date' => 'nullable|date',
            'parent_id' => 'nullable|exists:stock_orders,id',
            'cancel_reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'nullable|array|min:1',
            'items.*.product_sku' => 'required_with:items|exists:products,sku',
            'items.*.qty_ordered' => 'required_with:items|integer|min:1',
            'items.*.qty_fulfilled' => 'nullable|integer|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ];
    }
}
