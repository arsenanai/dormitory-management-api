<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @coversDefaultClass \App\Http\Controllers\UserController
 */
class EmailAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for consistent testing
        Role::factory()->create([ 'name' => 'student' ]);
        Role::factory()->create([ 'name' => 'admin' ]);
        Role::factory()->create([ 'name' => 'sudo' ]);
    }

    /** @test */
    public function it_returns_true_for_an_available_email()
    {
        $response = $this->getJson('/api/email/check-availability?email=newuser@example.com');

        $response->assertOk()
            ->assertJson([ 'is_available' => true ]);
    }

    /** @test */
    public function it_returns_false_for_an_unavailable_email()
    {
        User::factory()->create([ 'email' => 'existing@example.com' ]);

        $response = $this->getJson('/api/email/check-availability?email=existing@example.com');

        $response->assertOk()
            ->assertJson([ 'is_available' => false ]);
    }

    /** @test */
    public function it_returns_true_for_an_unavailable_email_when_ignoring_its_own_user_id()
    {
        $user = User::factory()->create([ 'email' => 'self@example.com' ]);

        $response = $this->getJson("/api/email/check-availability?email=self@example.com&ignore_user_id={$user->id}");

        $response->assertOk()
            ->assertJson([ 'is_available' => true ]);
    }

    /** @test */
    public function it_returns_false_for_an_unavailable_email_when_ignoring_a_different_user_id()
    {
        $user1 = User::factory()->create([ 'email' => 'user1@example.com' ]);
        $user2 = User::factory()->create([ 'email' => 'user2@example.com' ]);

        $response = $this->getJson("/api/email/check-availability?email=user1@example.com&ignore_user_id={$user2->id}");

        $response->assertOk()
            ->assertJson([ 'is_available' => false ]);
    }

    /** @test */
    public function it_returns_error_for_missing_email()
    {
        $response = $this->getJson('/api/email/check-availability');

        $response->assertStatus(422)
            ->assertJsonValidationErrors([ 'email' ]);
    }

    /** @test */
    public function it_returns_error_for_invalid_email_format()
    {
        $response = $this->getJson('/api/email/check-availability?email=invalid-email');

        $response->assertStatus(422)
            ->assertJsonValidationErrors([ 'email' ]);
    }

    /** @test */
    public function it_returns_error_for_invalid_ignore_user_id()
    {
        $response = $this->getJson('/api/email/check-availability?email=test@example.com&ignore_user_id=abc');

        $response->assertStatus(422)
            ->assertJsonValidationErrors([ 'ignore_user_id' ]);
    }

    /** @test */
    public function it_returns_error_for_non_existent_ignore_user_id()
    {
        $response = $this->getJson('/api/email/check-availability?email=test@example.com&ignore_user_id=9999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors([ 'ignore_user_id' ]);
    }
}
