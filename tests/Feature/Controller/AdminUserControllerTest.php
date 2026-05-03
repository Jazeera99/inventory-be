<?php

namespace Tests\Feature\Controller;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->actingAsSuperadmin();
    }

    /**
     * Test userController@index response should have these attributes.
     */
    public function test_index_attributes(): void
    {
        $user = User::factory()->create([
            'full_name' => 'Ahmad Fauzan',
        ]);

        $this->url(route('admin.user.index'));
        $this->assertJsonGet(fn (AssertableJson $json) => $json
            ->has('data', 2)
            ->has('data.0', fn (AssertableJson $json) => $json
                ->where('id', $user->id)
                ->where('username', $user->username)
                ->where('full_name', $user->full_name)
                ->has('role')
                ->etc()
            )
            ->paginated(2)
        );
    }

    /**
     * Test userController@index response should be ordered by .
     */
    public function test_index_order_by(): void
    {
        $user1 = User::factory()->create(['full_name' => 'Ahmad']);
        $user2 = User::factory()->create(['full_name' => 'Ziella']);

        $this->url(route('admin.user.index'));
        $this->assertJsonGet(fn (AssertableJson $json) => $json
            ->has('data', 3)
            ->where('data.0.id', $user1->id)
            ->where('data.2.id', $user2->id)
            ->etc()
        );
    }

    /**
     * Test search.
     */
    public function test_index_search(): void
    {
        $user = User::factory()->create(['full_name' => 'Laura Diva']);
        $user = User::factory()->create(['full_name' => 'Arabella Natalia']);

        $this->url(route('admin.user.index'));
        $this->url(route('admin.user.index', ['search' => 'Laura']));
        $this->assertJsonGet(fn (AssertableJson $json) => $json
            ->has('data', 1)
            ->where('data.0.full_name', 'Laura Diva')
            ->etc()
        );
    }

    /**
     * Test UsernameController@store.
     */
    public function test_store(): void
    {
        $role = Role::first();

        $form = [
            'username' => 'laura.admin',
            'password' => 'password123',
            'full_name' => 'Laura Developer',
            'role_id' => $role->id,
        ];

        $this->url(route('admin.user.store'));
        $this->assertJsonPost($form, fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $json) => $json
                ->has('id')
                ->where('username', $form['username'])
                ->where('full_name', $form['full_name'])
                ->has('role')
                ->etc()
            )
        );

        $this->assertDatabaseHas('users', [
            'username' => $form['username'],
            'full_name' => $form['full_name'],
            'role_id' => $form['role_id'],
        ]);
    }

    /**
     * Test userController@update.
     */
    public function test_update(): void
    {
        $user = User::factory()->create();
        $role = Role::first();

        $form = [
            'username' => 'user.edit',
            'full_name' => 'Nama Terupdate',
            'role_id' => $role->id,
        ];

        $this->url(route('admin.user.update', $user));
        $this->assertJsonPut($form, fn (AssertableJson $json) => $json
            ->has('data', fn (AssertableJson $json) => $json
                ->where('id', $user->id)
                ->where('username', $form['username'])
                ->where('full_name', $form['full_name'])
                ->etc()
            )
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => $form['username'],
            'full_name' => $form['full_name'],
        ]);
    }

    /**
     * Test Toggle Status User.
     */
    public function test_toggle_status(): void
    {
        // Buat user baru untuk dites
        $user = User::factory()->create(['is_active' => true]);

        $this->url(route('admin.user.toggle-status', $user));

        // Request pertama: Ubah jadi Non-Aktif (false)
        $this->assertJsonPatch([], fn (AssertableJson $json) => $json
            ->where('id', $user->id)
            ->where('is_active', false)
            ->where('message', 'Status user berhasil diubah.')
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);

        $this->assertJsonPatch([], fn (AssertableJson $json) => $json
            ->where('is_active', true)
            ->etc()
        );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => true,
        ]);
    }
}
