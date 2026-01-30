<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dormitory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @coversDefaultClass \App\Http\Controllers\AdminController
 */
class AdminDormitoryOptionalTest extends TestCase
{
    use RefreshDatabase;

    private Role $sudoRole;
    private User $sudo;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin role first (required by AdminController constructor)
        Role::firstOrCreate([ 'name' => 'admin' ]);

        $this->sudoRole = Role::firstOrCreate([ 'name' => 'sudo' ]);
        $this->sudo = User::factory()->create([ 'role_id' => $this->sudoRole->id ]);
    }

    /** @covers \App\Http\Controllers\AdminController::store
     * @covers \App\Services\AdminService
     */
    public function test_admin_can_be_created_without_dormitory(): void
    {
        $response = $this->actingAs($this->sudo)->postJson('/api/admins', [
            'first_name'            => 'John',
            'last_name'             => 'Admin',
            'email'                 => 'admin@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'phone_numbers'         => [ '+77001234567' ],
            // No dormitory_id provided
        ]);

        $response->assertStatus(201);
        $adminData = $response->json();
        $this->assertNull($adminData['dormitory_id'] ?? null);

        $this->assertDatabaseHas('users', [
            'email'        => 'admin@example.com',
            'dormitory_id' => null,
        ]);
    }

    /** @covers \App\Http\Controllers\AdminController::store
     * @covers \App\Services\AdminService
     * @covers \App\Services\DormitoryService
     */
    public function test_admin_can_be_created_with_dormitory(): void
    {
        $dormitory = Dormitory::factory()->create();

        $response = $this->actingAs($this->sudo)->postJson('/api/admins', [
            'first_name'            => 'Jane',
            'last_name'             => 'Admin',
            'email'                 => 'jane@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'phone_numbers'         => [ '+77001234568' ],
            'dormitory_id'          => $dormitory->id,
        ]);

        $response->assertStatus(201);
        $adminData = $response->json();
        // Verify admin was created and assigned to dormitory (via dormitory.admin_id)
        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
        ]);
        $dormitory->refresh();
        $this->assertEquals($adminData['id'], $dormitory->admin_id);
    }

    /** @covers \App\Http\Controllers\AdminController::update
     * @covers \App\Services\AdminService
     */
    public function test_admin_can_be_updated_with_null_dormitory_id(): void
    {
        $adminRole = Role::firstOrCreate([ 'name' => 'admin' ]);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $response = $this->actingAs($this->sudo)->putJson("/api/admins/{$admin->id}", [
            'first_name'    => 'John',
            'last_name'     => 'Admin',
            'email'         => 'admin@example.com',
            'phone_numbers' => [ '+77001234567' ],
            'dormitory_id'  => null, // Null is accepted (field is optional)
        ]);

        $response->assertStatus(200);
        // The key point is that validation accepts null dormitory_id
    }

    /** @covers \App\Http\Controllers\AdminController::update
     * @covers \App\Services\AdminService
     * @covers \App\Services\DormitoryService
     */
    public function test_admin_can_be_updated_to_add_dormitory(): void
    {
        $adminRole = Role::firstOrCreate([ 'name' => 'admin' ]);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $dormitory = Dormitory::factory()->create([ 'admin_id' => null ]);

        $response = $this->actingAs($this->sudo)->putJson("/api/admins/{$admin->id}", [
            'first_name'    => 'John',
            'last_name'     => 'Admin',
            'email'         => 'admin@example.com',
            'phone_numbers' => [ '+77001234567' ],
            'dormitory_id'  => $dormitory->id,
        ]);

        $response->assertStatus(200);
        // Verify admin was assigned to dormitory (via dormitory.admin_id)
        $dormitory->refresh();
        $this->assertEquals($admin->id, $dormitory->admin_id);
    }

    /** @covers \App\Http\Controllers\AdminController::store
     * @covers \App\Services\AdminService
     */
    public function test_admin_validation_allows_null_dormitory_id(): void
    {
        // This test verifies that validation doesn't reject null dormitory_id
        $response = $this->actingAs($this->sudo)->postJson('/api/admins', [
            'first_name'            => 'Test',
            'last_name'             => 'Admin',
            'email'                 => 'testadmin@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'phone_numbers'         => [ '+77001234569' ],
            'dormitory_id'          => null, // Explicitly set to null
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email'        => 'testadmin@example.com',
            'dormitory_id' => null,
        ]);
    }

    /** @covers \App\Http\Controllers\AdminController::store
     * @covers \App\Services\AdminService
     */
    public function test_admin_validation_rejects_invalid_dormitory_id(): void
    {
        $response = $this->actingAs($this->sudo)->postJson('/api/admins', [
            'first_name'            => 'Test',
            'last_name'             => 'Admin',
            'email'                 => 'testadmin2@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'phone_numbers'         => [ '+77001234570' ],
            'dormitory_id'          => 99999, // Non-existent dormitory
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([ 'dormitory_id' ]);
    }
}
