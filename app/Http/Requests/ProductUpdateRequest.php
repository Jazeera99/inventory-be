<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('category_id') || ! is_numeric($this->category_id)) {
            $product = $this->route('product');
            if ($product) {
                $this->merge([
                    'category_id' => $product->category_id,
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productSku = $product ? $product->sku : null;
        $inputCategoryId = $this->input('category_id');

        return [
            'sku' => 'nullable|string|max:50',
            'product_name' => 'required|string|max:255',
            // Saat update, kategori yang dipilih juga wajib berstatus aktif
            'category_id' => [
                'required',
                function ($attribute, $value, $fail) use ($product, $inputCategoryId): void {
                    // JIKA user tidak mengubah kategori (ID yang dikirim sama dengan ID produk saat ini)
                    // Maka abaikan pengecekan status is_active (Biar user bisa bebas edit stok)
                    if ($product && $product->category_id == $inputCategoryId) {
                        return;
                    }

                    // JIKA user mengganti kategori baru, baru pastikan ID-nya ada dan harus AKTIF
                    $categoryExists = \DB::table('categories')
                        ->where('id', $value)
                        ->where('is_active', 1)
                        ->exists();

                    if (! $categoryExists) {
                        $fail(__('validation.exists', ['attribute' => 'kategori']));
                    }
                },
            ],
            'brand' => 'required|string|min:3|max:50',
            'type' => 'nullable|string|max:50',
            'packaging' => 'required|string|max:50',
            'size' => 'required|string|max:50',
            'purchase_price' => 'sometimes|numeric|min:0',
            'selling_price' => 'sometimes|numeric|min:0',
            'holding_cost_per_day' => 'sometimes|numeric|min:0',
            'min_stock' => 'required|integer|min:0',
            'exp_warning_days' => 'sometimes|integer|min:0',
        ];
    }
}
