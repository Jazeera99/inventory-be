<?php

namespace Tests\Feature\Request;

use App\Models\Rack;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class RackUpdateRequestTest extends TestCase
{
    protected Rack $existingRack;

    protected string $url;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create([
            'id' => 1,
            'role_name' => 'SuperAdmin',
            'permissions' => ['Manajemen Rak'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $user->setRelation('role', $role);
        $this->actingAs($user);

        // Buat satu contoh rak awal untuk target update
        $this->existingRack = Rack::factory()->create([
            'rack_name' => 'A',
            'column_number' => 1,
            'level_number' => 3,
            'location_code' => 'A1-3',
            'capacity' => 15,
        ]);

        $this->url(route('admin.rack.update', ['rack' => $this->existingRack->id]), 'PUT');
    }

    /**
     * Test validation for required main fields (rack_name, column_number, level_number, location_code).
     */
    public function test_required_fields_on_update(): void
    {
        $form = [];
        $this->assertJsonReqErrors($form, [
            'rack_name' => __('validation.required'),
            'column_number' => __('validation.required'),
            'level_number' => __('validation.required'),
        ]);
    }

    /**
     * Test validation for unique location_code on update (should ignore current rack).
     */
    public function test_cannot_update_to_another_existing_rack_location(): void
    {
        // Buat rak lain yang menduduki slot A2-1
        Rack::factory()->create(['location_code' => 'A2-1']);

        // Coba ubah rak target kita (A1-3) agar berpindah kolom ke 2 dan tingkat ke 1 (menjadi A2-1)
        $form = [
            'rack_name' => 'A',
            'column_number' => 2, // Mengubah kolom ke 2
            'level_number' => 1,  // Mengubah tingkat ke 1
            'capacity' => 15,
        ];

        // Sistem harus mendeteksi konflik unik pada 'location_code' hasil generate otomatis backend
        $this->assertJsonReqErrors($form, [
            'location_code' => __('validation.unique'),
        ]);
    }

    /**
     * Test error message when rack_name is too long or not a string.
     */
    public function test_rack_name_validation(): void
    {
        $form = [
            'rack_name' => str_repeat('A', 101), // Melebihi batas maksimal 100 karakter
            'column_number' => 1,
            'level_number' => 3,
            'capacity' => 15,
        ];

        $this->assertJsonReqErrors($form, [
            'rack_name' => __('validation.max.string', ['max' => 100]),
        ]);
    }

    /**
     * Test validation for numeric fields (column_number and level_number must be integers and greater than 0).
     */
    public function test_numeric_fields_more_than_0(): void
    {
        $form = [
            'rack_name' => 'B',
            'column_number' => '0',
            'level_number' => '0',
            'capacity' => '0',
        ];

        $this->assertJsonReqErrors($form, [
            'column_number' => __('validation.min.numeric'),
            'level_number' => __('validation.min.numeric'),
            'capacity' => __('validation.min.numeric', ['min' => 1]),
        ]);
    }

    /**
     * Test validation for numeric fields (capacity must be less than 25).
     */
    public function test_capacity_must_be_less_than_25(): void
    {
        $form = [
            'rack_name' => 'C',
            'column_number' => 1,
            'level_number' => 1,
            'capacity' => 26, // Melebihi kapasitas maksimal 15
        ];

        $this->assertJsonReqErrors($form, [
            'capacity' => __('validation.max.numeric', ['max' => 25]),
        ]);
    }

    /**
     * Test successful update with valid data.
     */
    public function test_successful_update(): void
    {
        $form = [
            'rack_name' => 'A',
            'column_number' => 1, // Tetap kolom 1
            'level_number' => 3,  // Tetap tingkat 3
            'capacity' => 20,     // Hanya menaikkan kapasitas tampung barang
        ];

        $response = $this->putJson($this->url, $form);
        $response->assertStatus(200);
    }

    /**
     * Test validation for data types (column_number and level_number must be integers).
     */
    public function test_data_types_on_update(): void
    {
        $form = [
            'rack_name' => 99999,
            'column_number' => 'string-bukan-angka',
            'level_number' => 'string-bukan-angka',
        ];

        $this->assertJsonReqErrors($form, [
            'rack_name' => __('validation.string'),
            'column_number' => __('validation.integer'),
            'level_number' => __('validation.integer'),
        ]);
    }
}
