<?php

namespace Tests\Feature;

use App\Models\Dormitory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private $sudoRoleId;
    private $adminRoleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sudoRoleId = Role::factory()->create(['name' => 'sudo'])->id;
        $this->adminRoleId = Role::factory()->create(['name' => 'admin'])->id;
    }

    private function loginAsSudo()
    {
        $sudo = User::factory()->create(['role_id' => $this->sudoRoleId]);
        return $sudo->createToken('test-token')->plainTextToken;
    }

    public function test_sudo_can_list_admins()
    {
        User::factory()->count(3)->create(['role_id' => $this->adminRoleId]);
        $token = $this->loginAsSudo();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admins');

        $response->assertOk()->assertJsonCount(3);
    }

    public function test_sudo_can_create_admin()
    {
        $token = $this->loginAsSudo();

        $payload = [
            'dormitory'       => Dormitory::factory()->create()->id,
            'name'            => 'Test Admin',
            'email'           => 'testadmin@example.com',
            'password'        => 'password',
            'role_id'         => $this->adminRoleId,
            'phone_numbers'   => ['1234567890'],
            'position'        => 'Dormitory Manager',
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/admins', $payload);

        $response->assertStatus(201)->assertJsonFragment(['email' => 'testadmin@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'testadmin@example.com']);
        $this->assertDatabaseHas('admin_profiles', ['position' => 'Dormitory Manager']);
    }

    public function test_sudo_can_update_admin()
    {
        $token = $this->loginAsSudo();
        $admin = User::factory()->create(['role_id' => $this->adminRoleId]);
        $dormitory = Dormitory::factory()->create();

        $payload = [
            'name' => 'Updated Admin Name',
            'email' => 'updated@example.com',
            'dormitory' => $dormitory->id,
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson("/api/admins/{$admin->id}", $payload);

        $response->assertOk()->assertJsonFragment(['name' => 'Updated Admin Name']);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => 'Updated Admin Name']);
    }

    public function test_sudo_can_delete_admin()
    {
        $token = $this->loginAsSudo();
        $admin = User::factory()->create(['role_id' => $this->adminRoleId]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson("/api/admins/{$admin->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $admin->id, 'deleted_at' => null]);
    }

    // Add other tests from the original file if they existed...
}