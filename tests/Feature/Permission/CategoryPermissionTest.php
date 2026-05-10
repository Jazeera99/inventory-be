<?php

namespace Tests\Feature\Permission;

use App\Models\Category;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class CategoryPermissionTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /**
     * Test access for list categories.
     */
    public function test_index(): void
    {
        $url = route('admin.category.index');
        $this->url($url);

        $this->assertUserPermission(fn () => $this->getJson($url))
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for create category.
     */
    public function test_store(): void
    {
        $url = route('admin.category.store');
        $this->url($url);

        $this->assertUserPermission(function () use ($url) {
            $form = [
                'category_name' => 'Kategori Baru '.uniqid(),
                'description' => 'Deskripsi kategori',
                'is_active' => true,
            ];

            return $this->postJson($url, $form);
        })
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for update category.
     */
    public function test_update(): void
    {
        $category = Category::factory()->create();

        $url = route('admin.category.update', $category);
        $this->url($url);

        $this->assertUserPermission(function () use ($url) {
            $form = [
                'category_name' => 'Kategori Edit '.uniqid(),
                'description' => 'Deskripsi edit',
                'is_active' => true,
            ];

            return $this->putJson($url, $form);
        })
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }

    /**
     * Test access for toggle active status.
     */
    public function test_toggle_active(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $url = route('admin.category.toggle-active', $category);
        $this->url($url);

        $this->assertUserPermission(fn () => $this->patchJson($url))
            ->allow($this->createSuperadmin())
            ->allow($this->createWarehouseAdmin())
            ->forbid($this->createStaff());
    }
}
