<?php

namespace Tests\Feature\Controller;

use App\Models\Category;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Jalankan seeder role agar user admin bisa dibuat (mengikuti setup kamu)
        $this->seed(RoleSeeder::class);

        $this->actingAsSuperadmin();
    }

    /**
     * Test CategoryController@index attributes.
     */
    public function test_index_attributes(): void
    {
        $category = Category::factory()->create([
            'category_name' => 'Sembako',
            'description' => 'Kebutuhan pokok',
            'is_active' => true,
        ]);

        $this->url(route('admin.category.index'));
        $this->assertJsonGet(fn (AssertableJson $json) => $json
            ->has('data', 1)
            ->has('data.0', fn (AssertableJson $json) => $json
                ->where('id', $category->id)
                ->where('category_name', $category->category_name)
                ->where('description', $category->description)
                ->where('is_active', true)
                ->etc()
            )
            ->paginated()
        );
    }

    /**
     * Test Category search by name.
     */
    public function test_index_search(): void
    {
        Category::factory()->create(['category_name' => 'Elektronik']);
        Category::factory()->create(['category_name' => 'Pakaian']);

        $this->url(route('admin.category.index', ['search' => 'Elek']));
        $this->assertJsonGet(fn (AssertableJson $json) => $json
            ->has('data', 1)
            ->where('data.0.category_name', 'Elektronik')
            ->etc()
        );
    }

    /**
     * Test CategoryController@store.
     */
    public function test_store(): void
    {
        $form = [
            'category_name' => 'Perabotan',
            'description' => 'Alat rumah tangga',
            'is_active' => true,
        ];

        $this->url(route('admin.category.store'));
        $this->assertJsonPost($form, fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $json) => $json
                ->has('id')
                ->where('category_name', $form['category_name'])
                ->where('description', $form['description'])
                ->where('is_active', true)
                ->etc()
            )
        );

        $this->assertDatabaseHas('categories', [
            'category_name' => $form['category_name'],
            'description' => $form['description'],
        ]);
    }

    /**
     * Test CategoryController@update.
     */
    public function test_update(): void
    {
        $category = Category::factory()->create();

        $form = [
            'category_name' => 'Nama Kategori Baru',
            'description' => 'Deskripsi Terupdate',
            'is_active' => false,
        ];

        $this->url(route('admin.category.update', $category));
        $this->assertJsonPut($form, fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $json) => $json
                ->where('id', $category->id)
                ->where('category_name', $form['category_name'])
                ->where('is_active', false)
                ->etc()
            )
        );

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'category_name' => $form['category_name'],
            'is_active' => false,
        ]);
    }

    /**
     * Test Toggle Active Status Category.
     */
    public function test_toggle_active(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $this->url(route('admin.category.toggle-active', $category));

        // Request pertama: Ubah jadi false
        $this->assertJsonPatch([], fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $json) => $json
                ->where('id', $category->id)
                ->where('is_active', false)
                ->etc()
            )
        );

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);

        // Request kedua: Balikkan jadi true
        $this->assertJsonPatch([], fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $json) => $json
                ->where('is_active', true)
                ->etc()
            )
        );
    }
}
