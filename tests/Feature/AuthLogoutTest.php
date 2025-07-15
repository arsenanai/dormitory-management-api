<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutTest extends TestCase {
    use RefreshDatabase;

    public function test_user_can_logout_successfully() {
        // Given: A user is logged in
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // When: User calls logout endpoint
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->post('/api/logout');

        // Then: Should get successful response
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Logged out successfully'
        ]);

        // And: Token should be invalidated
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_logout_requires_authentication() {
        // When: Unauthenticated user tries to logout
        $response = $this->postJson('/api/logout');

        // Then: Should return 401 unauthorized
        $response->assertStatus(401);
    }

    public function test_logout_with_invalid_token_returns_401() {
        // When: User tries to logout with invalid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
            'Accept' => 'application/json',
        ])->post('/api/logout');

        // Then: Should return 401 unauthorized
        $response->assertStatus(401);
    }
}
