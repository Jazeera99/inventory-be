<?php

namespace Tests\Feature\Request;

use App\Models\Rack;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class RackStoreRequestTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Buat user yang memiliki permission 'Manajemen Rak'
        $role = Role::factory()->create([
            'id' => 1,
            'role_name' => 'SuperAdmin',
            'permissions' => ['Manajemen Rak'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        // Refresh relasi agar middleware & gate bisa baca
        $user->setRelation('role', $role);

        $this->actingAs($user);
        $this->url(route('admin.rack.store'), 'POST');
    }

    /**
     * Test error message when field is not provided or empty.
     */
    public function test_required(): void
    {
        $form = [];
        $this->assertJsonReqErrors($form, [
            'location_code' => __('validation.required'),
            'rack_name' => __('validation.required'),
            'column_number' => __('validation.required'),
            'level_number' => __('validation.required'),
        ]);
    }

    /**
     * Test error message when location_code is already exist.
     */
    public function test_location_code_must_be_unique(): void
    {
        Rack::factory()->create(['location_code' => 'A1-1']);

        $form = [
            'location_code' => 'A1-1',
            'rack_name' => 'Rak Baru',
            'column_number' => 1,
            'level_number' => 2,
        ];

        $this->assertJsonReqErrors($form, [
            'location_code' => __('validation.unique'),
        ]);
    }

    /**
     * Test error message when data type is wrong.
     */
    public function test_data_types(): void
    {
        $form = [
            'location_code' => 12345,
            'rack_name' => 12345,
            'column_number' => 'bukan-angka',
            'level_number' => 'bukan-angka',
        ];

        $this->assertJsonReqErrors($form, [
            'location_code' => __('validation.string'),
            'rack_name' => __('validation.string'),
            'column_number' => __('validation.integer'),
            'level_number' => __('validation.integer'),
        ]);
    }

    /**
     * Test error message when string length exceeds limit.
     */
    public function test_max_characters(): void
    {
        $form = [
            'location_code' => str_repeat('A', 256),
            'rack_name' => str_repeat('B', 101),
            'column_number' => 1,
            'level_number' => 1,
        ];

        $this->assertJsonReqErrors($form, [
            'location_code' => __('validation.max.string', ['max' => 255]),
            'rack_name' => __('validation.max.string', ['max' => 100]),
        ]);
    }

    /**
     * Test successful validation with valid data.
     */
    public function test_valid_request(): void
    {
        $form = [
            'location_code' => 'C1-'.uniqid(),
            'rack_name' => 'Rak Gudang Utama',
            'column_number' => 5,
            'level_number' => 3,
        ];

        $response = $this->postJson($this->url, $form);

        $response->assertStatus(201);
    }

    /**
     * Test error message when field is not an integer.
     */
    public function test_integer(): void
    {
        $form = [
            'column_number' => 'some string',
            'level_number' => 'some string',
        ];
        $this->assertJsonReqErrors($form, [
            'column_number' => __('validation.integer'),
            'level_number' => __('validation.integer'),
        ]);
    }

    /**
     * Test error message when numeric fields are less than 1.
     */
    public function test_min_values(): void
    {
        $form = [
            'location_code' => 'A1-0',
            'rack_name' => 'Rak Test',
            'column_number' => 0,
            'level_number' => -5,
        ];

        $this->assertJsonReqErrors($form, [
            'column_number' => __('validation.min.numeric', ['min' => 1]),
            'level_number' => __('validation.min.numeric', ['min' => 1]),
        ]);
    }

    /**
     * Test error message for boolean fields.
     */
    public function test_boolean_types(): void
    {
        $form = [
            'location_code' => 'A1-1',
            'rack_name' => 'Rak Test',
            'column_number' => 1,
            'level_number' => 1,
            'is_active' => 'bukan-boolean',
            'is_maintenance' => 123,
        ];

        $this->assertJsonReqErrors($form, [
            'is_active' => __('validation.boolean'),
            'is_maintenance' => __('validation.boolean'),
        ]);
    }
}
