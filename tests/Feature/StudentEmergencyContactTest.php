<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEmergencyContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'student']);
    }

    public function test_student_registration_accepts_new_emergency_contact_fields(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->first()->id]);
        $dormitory = \App\Models\Dormitory::factory()->create(['admin_id' => $admin->id]);

        $response = $this->actingAs($admin)->postJson('/api/students', [
            'email' => 'student@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'name' => 'John Doe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_numbers' => ['+77001234567'],
            'student_profile' => [
                'iin' => '123456789012',
                'faculty' => 'Engineering',
                'specialist' => 'Computer Science',
                'enrollment_year' => 2024,
                'gender' => 'male',
                'has_meal_plan' => false,
                'agree_to_dormitory_rules' => true,
                'emergency_contact_name' => 'Jane Doe',
                'emergency_contact_phone' => '+77001234568',
                'emergency_contact_type' => 'parent',
                'emergency_contact_email' => 'jane@example.com',
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('student_profiles', [
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '+77001234568',
            'emergency_contact_type' => 'parent',
            'emergency_contact_email' => 'jane@example.com',
        ]);
    }

    public function test_student_update_accepts_new_emergency_contact_fields(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->first()->id]);
        $dormitory = \App\Models\Dormitory::factory()->create(['admin_id' => $admin->id]);
        $student = User::factory()->create(['role_id' => Role::where('name', 'student')->first()->id]);
        $student->studentProfile()->create([
            'iin' => '123456789012',
            'student_id' => 'STU001',
            'faculty' => 'Engineering',
            'specialist' => 'Computer Science',
            'enrollment_year' => 2024,
            'gender' => 'male',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/students/{$student->id}", [
            'email' => $student->email,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'student_profile' => [
                'emergency_contact_name' => 'Updated Contact',
                'emergency_contact_phone' => '+77001234569',
                'emergency_contact_type' => 'guardian',
                'emergency_contact_email' => 'updated@example.com',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'emergency_contact_name' => 'Updated Contact',
            'emergency_contact_phone' => '+77001234569',
            'emergency_contact_type' => 'guardian',
            'emergency_contact_email' => 'updated@example.com',
        ]);
    }

    public function test_emergency_contact_type_validation_accepts_valid_values(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->first()->id]);
        $dormitory = \App\Models\Dormitory::factory()->create(['admin_id' => $admin->id]);

        $validTypes = ['parent', 'guardian', 'other'];

        foreach ($validTypes as $type) {
            $response = $this->actingAs($admin)->postJson('/api/students', [
                'email' => "student{$type}@example.com",
                'first_name' => 'John',
                'last_name' => 'Doe',
                'name' => 'John Doe',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'phone_numbers' => ['+77001234567'],
                'student_profile' => [
                    'iin' => fake()->unique()->numerify('############'),
                    'faculty' => 'Engineering',
                    'specialist' => 'Computer Science',
                    'enrollment_year' => 2024,
                    'gender' => 'male',
                    'has_meal_plan' => false,
                    'agree_to_dormitory_rules' => true,
                    'emergency_contact_type' => $type,
                ],
            ]);

            $response->assertStatus(201);
        }
    }

    public function test_emergency_contact_type_validation_rejects_invalid_values(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->first()->id]);
        $dormitory = \App\Models\Dormitory::factory()->create(['admin_id' => $admin->id]);

        $response = $this->actingAs($admin)->postJson('/api/students', [
            'email' => 'student@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'name' => 'John Doe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_numbers' => ['+77001234567'],
            'student_profile' => [
                'iin' => '123456789012',
                'faculty' => 'Engineering',
                'specialist' => 'Computer Science',
                'enrollment_year' => 2024,
                'gender' => 'male',
                'has_meal_plan' => false,
                'agree_to_dormitory_rules' => true,
                'emergency_contact_type' => 'invalid_type',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['student_profile.emergency_contact_type']);
    }
}
