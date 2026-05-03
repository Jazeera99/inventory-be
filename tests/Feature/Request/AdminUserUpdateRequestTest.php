<?php

namespace Tests\Feature\Request;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class AdminUserUpdateRequestTest extends TestCase
{
    protected User $User;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->actingAsSuperadmin();

        $this->User = User::factory()->create([
            'username' => 'lauratech',
        ]);

        $this->url(route('admin.user.update', $this->User), 'PUT');
    }

    /**
     * Test update validation required.
     */
    public function test_update_required(): void
    {
        $form = [];
        $this->assertJsonPutErrors($form, [
            'username' => __('validation.required'),
            'full_name' => __('validation.required'),
            'role_id' => __('validation.required'),
        ]);
    }

    /**
     * Test error message when username is already exist in database.
     */
    public function test_unique_ignores_self(): void
    {
        $form = [
            'username' => $this->User->username,
            'full_name' => 'Laura Diva',
            'role_id' => $this->User->role_id,
        ];
        $this->assertJsonPut($form, fn ($json) => $json->etc());
    }

    /**
     * Test error message when username is already taken by another user.
     */
    public function test_unique_error_if_taken_by_others(): void
    {
        User::factory()->create(['username' => 'admin_nih']);

        $form = [
            'username' => 'admin_nih', // Username already exists in database
            'full_name' => 'Laura Diva',
            'role_id' => $this->User->role_id,
        ];
        $this->assertJsonReqErrors($form, [
            'username' => __('validation.unique'),
        ]);
    }
}
