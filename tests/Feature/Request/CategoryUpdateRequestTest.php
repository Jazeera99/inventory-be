<?php

namespace Tests\Feature\Request;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class CategoryUpdateRequestTest extends TestCase
{
    protected Category $category;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->actingAsSuperadmin();

        $this->category = Category::factory()->create([
            'category_name' => 'Kategori Lama',
        ]);

        $this->url(route('admin.category.update', $this->category), 'PUT');
    }

    /**
     * Test error message when required fields are missing.
     */
    public function test_required(): void
    {
        $form = [
            'category_name' => '',
            'is_active' => '',
        ];

        $this->assertJsonReqErrors($form, [
            'category_name' => __('validation.required'),
            'is_active' => __('validation.required'),
        ]);
    }

    /**
     * Should not display error message if category name remains the same.
     */
    public function test_unique_ignore_current_id(): void
    {
        $form = [
            'category_name' => 'Kategori Lama',
            'is_active' => true,
        ];

        $response = $this->json('PUT', $this->url, $form);

        $response->assertStatus(200);
    }

    /**
     * Error if update uses another existing category name.
     */
    public function test_category_name_must_be_unique(): void
    {
        Category::factory()->create(['category_name' => 'Kategori Lain']);

        $form = [
            'category_name' => 'Kategori Lain',
            'is_active' => true,
        ];

        $this->assertJsonReqErrors($form, [
            'category_name' => __('validation.unique'),
        ]);
    }

    /**
     * Security: Verify staff cannot update.
     */
    public function test_only_authorized_roles_can_access(): void
    {
        $user = User::factory()->create(['role_id' => 3]);

        $this->actingAs($user);

        $form = [
            'category_name' => 'Kategori Edit',
            'is_active' => true,
        ];

        $response = $this->json('PUT', $this->url, $form);

        $response->assertStatus(403);
    }
}
