<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RackUpdateRequest extends FormRequest
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
        $rackName = $this->rack_name;

        if ($rackName) {
            // Bersihkan string jika user menulis "Rak A" -> diambil "A" saja
            if (str_starts_with(strtolower($rackName), 'rak ')) {
                $rackName = substr($rackName, 4);
            }
            $rackName = trim($rackName);
        }

        // Paksa timpa atau buat value 'location_code' baru berdasarkan perubahan kolom & tingkat
        if ($rackName && $this->filled(['column_number', 'level_number'])) {
            $this->merge([
                'location_code' => strtoupper($rackName).$this->column_number.'-'.$this->level_number,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rack = $this->route('rack');
        $rackId = $rack ? $rack->id : null;

        $isInputLoadingDock = str_contains(strtolower($this->rack_name), 'loading') || str_contains(strtolower($this->rack_name), 'ld');
        $isDbLoadingDock = $rack && (str_contains(strtolower($rack->rack_name), 'loading') || str_contains(strtolower($rack->location_code), 'ld'));

        // Jika salah satu terpenuhi, berarti ini adalah area Loading Dock
        $isLoadingDock = $isInputLoadingDock || $isDbLoadingDock;

        // 2. Tentukan batas maksimal kapasitas secara dinamis
        $minNumber = $isLoadingDock ? 'min:0' : 'min:1';
        $maxCapacity = $isLoadingDock ? 50 : 25;

        return [
            'rack_name' => 'required|string',
            'column_number' => "required|integer|{$minNumber}",
            'level_number' => "required|integer|{$minNumber}",
            'location_code' => 'required|string|unique:racks,location_code,'.$rackId,
            'capacity' => "required|integer|min:1|max:{$maxCapacity}",
            'is_active' => 'sometimes|boolean',
            'is_maintenance' => 'sometimes|boolean',
        ];
    }

    /**
     * Custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            // Jika bukan LD dan diisi 0, kita tembak dengan pesan 'required' atau kustom sesuai keinginanmu
            'column_number.min' => __('validation.required'),
            'level_number.min' => __('validation.required'),
        ];
    }
}
