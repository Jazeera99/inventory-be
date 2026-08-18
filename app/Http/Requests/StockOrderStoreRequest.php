<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StockOrderStoreRequest extends FormRequest
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
            'type' => 'required|in:INBOUND,OUTBOUND,RETURN_IN,RETURN_OUT',
            'supplier_id' => 'required_if:type,INBOUND,RETURN_OUT|nullable|exists:suppliers,id',
            'customer_id' => 'required_if:type,OUTBOUND,RETURN_IN|nullable|exists:customers,id',
            'status' => 'nullable|in:DRAFT',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'parent_id' => 'required_if:type,RETURN_IN,RETURN_OUT|nullable|exists:stock_orders,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_sku' => 'required|exists:products,sku',
            'items.*.qty_ordered' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required_if' => 'Supplier wajib dipilih untuk order INBOUND (Purchase Order).',
            'customer_id.required_if' => 'Customer wajib dipilih untuk order OUTBOUND (Sales Order).',
            'items.required' => 'Minimal harus menambahkan 1 barang.',
            'parent_id.required_if' => 'Dokumen asal wajib dipilih untuk retur.',
        ];
    }
}
