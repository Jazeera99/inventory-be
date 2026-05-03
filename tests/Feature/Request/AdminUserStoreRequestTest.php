<?php

namespace Tests\Feature\Request;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class AdminUserStoreRequestTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->actingAsSuperadmin();

        $this->url(route('admin.user.store'), 'POST');
    }

    /**
     * Test error message when field is not provided or empty.
     */
    public function test_required(): void
    {
        $form = [];
        // $this->withoutExceptionHandling();
        $this->assertJsonReqErrors($form, [
            'username' => __('validation.required'),
            'password' => __('validation.required'),
            'full_name' => __('validation.required'),
            'role_id' => __('validation.required'),
        ]);
    }

    /**
     * Test error message when role_id is not exist.
     */
    public function test_role_id_exists(): void
    {
        $form = [
            'role_id' => 99,
        ];

        $this->assertJsonPostErrors($form, [
            'role_id' => __('validation.exists'),
        ]);
    }

    /**
     * Test error message when field is already exist in database.
     */
    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'lauratech']);

        $form = [
            'username' => 'lauratech', // Username already exists in database
            'password' => 'password123',
            'full_name' => 'Laura Diva',
            'role_id' => 1,
        ];

        $this->assertJsonReqErrors($form, [
            'username' => __('validation.unique'),
        ]);
    }

    /**
     * Test error message when field is not exist in database.
     */
    public function test_exist(): void
    {
        $form = [
            'username' => 'newuser',
            'role_id' => 999, // ID role not found in database
        ];
        $this->assertJsonReqErrors($form, [
            'role_id' => __('validation.exists'),
        ]);
    }

    /**
     * Test error message when password is below minimum requirement.
     */
    public function test_min_length(): void
    {
        $randomString = str()->random(5); // Generate a random string with length of 5 characters
        $form = [
            'password' => $randomString,
        ];
        $this->assertJsonReqErrors($form, [
            'password' => __('validation.min.string', ['min' => 8]),
        ]);
    }
}
