<?php

namespace Tests\Feature\Request;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class CategoryStoreRequestTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->actingAsSuperadmin();

        $this->url(route('admin.category.store'), 'POST');
    }

    /**
     * Test error message when field is not provided or empty.
     */
    public function test_required(): void
    {
        $form = [];
        $this->assertJsonReqErrors($form, [
            'category_name' => __('validation.required'),
        ]);
    }

    /**
     * Test error message when category_name is already exist.
     */
    public function test_category_name_must_be_unique(): void
    {
        Category::factory()->create(['category_name' => 'Sembako']);

        $form = [
            'category_name' => 'Sembako',
        ];

        $this->assertJsonReqErrors($form, [
            'category_name' => __('validation.unique'),
        ]);
    }

    /**
     * Test security: Only superadmin and warehouse admin can access.
     */
    public function test_only_authorized_roles_can_access(): void
    {
        // Role ID 3 biasanya Staff (sesuaikan dengan seeder kamu)
        $user = User::factory()->create(['role_id' => 3]);

        $this->actingAs($user);

        $form = [
            'category_name' => 'Elektronik',
        ];

        $response = $this->json('POST', route('admin.category.store'), $form);

        $response->assertStatus(403);
    }
}
