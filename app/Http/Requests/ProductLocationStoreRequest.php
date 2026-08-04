<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductLocationStoreRequest extends FormRequest
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
            'product_sku' => 'required|exists:products,sku',
            'rack_id' => 'required|exists:racks,id',
            'qty' => 'required|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0',
            'expired_at' => 'required|date|after:today',
            'status' => 'nullable|in:AVAILABLE,QUARANTINE,EXPIRED_RETUR',
        ];
    }
}
