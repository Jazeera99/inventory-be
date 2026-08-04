<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RackStoreRequest extends FormRequest
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
        $isLoadingDock = str_contains(strtolower($rackName), 'loading') || str_contains(strtolower($rackName), 'ld');

        if ($isLoadingDock) {
            // Jika diedit menjadi Loading Dock dan kodenya belum berformat 'LD-',
            // kosongkan saja agar model mentrigger penomoran ulang otomatis
            if ($this->filled('location_code') && ! str_starts_with($this->location_code, 'LD-')) {
                $this->merge(['location_code' => null]);
            }

            return; // Hentikan eksekusi logika koordinat di bawah
        }

        if ($rackName) {
            if (str_starts_with(strtolower($rackName), 'rak ')) {
                $rackName = substr($rackName, 4);
            }
            $rackName = trim($rackName);
        }

        if ($rackName && $this->filled(['column_number', 'level_number'])) {
            $this->merge([
                'location_code' => strtoupper($rackName).$this->column_number.'-'.$this->level_number,
            ]);
        }
        // if (! $this->filled('location_code') && $this->filled(['rack_name', 'column_number', 'level_number'])) {
        //     $this->merge([
        //         'location_code' => strtoupper($this->rack_name).$this->column_number.'-'.$this->level_number,
        //     ]);
        // }
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
        $maxCapacity = $isLoadingDock ? 999999 : 25;

        return [
            'location_code' => 'nullable|string|unique:racks,location_code,'.$rackId,
            'rack_name' => 'required|string|max:100',
            'column_number' => "required|integer|{$minNumber}",
            'level_number' => "required|integer|{$minNumber}",
            'capacity' => "required|integer|min:1|max:{$maxCapacity}",
            'is_active' => 'boolean',
            'is_maintenance' => 'boolean',
        ];
    }
}
