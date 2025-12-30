<?php

namespace Tests\Unit;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProfileModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_profile_can_be_created_with_emergency_contact_fields(): void
    {
        $user = User::factory()->create();

        $profile = StudentProfile::create([
            'user_id' => $user->id,
            'iin' => '123456789012',
            'student_id' => 'STU001',
            'faculty' => 'Engineering',
            'specialist' => 'Computer Science',
            'enrollment_year' => 2024,
            'gender' => 'male',
            'emergency_contact_name' => 'John Doe',
            'emergency_contact_phone' => '+77001234567',
            'emergency_contact_type' => 'parent',
            'emergency_contact_email' => 'parent@example.com',
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $user->id,
            'emergency_contact_name' => 'John Doe',
            'emergency_contact_phone' => '+77001234567',
            'emergency_contact_type' => 'parent',
            'emergency_contact_email' => 'parent@example.com',
        ]);

        $this->assertEquals('parent', $profile->emergency_contact_type);
        $this->assertEquals('parent@example.com', $profile->emergency_contact_email);
    }

    public function test_student_profile_accepts_all_emergency_contact_types(): void
    {
        $user = User::factory()->create();

        $types = ['parent', 'guardian', 'other'];

        foreach ($types as $type) {
            $profile = StudentProfile::create([
                'user_id' => $user->id,
                'iin' => fake()->unique()->numerify('############'),
                'student_id' => fake()->unique()->numerify('STU#####'),
                'faculty' => 'Engineering',
                'specialist' => 'Computer Science',
                'enrollment_year' => 2024,
                'gender' => 'male',
                'emergency_contact_name' => 'Contact Name',
                'emergency_contact_phone' => '+77001234567',
                'emergency_contact_type' => $type,
                'emergency_contact_email' => 'contact@example.com',
            ]);

            $this->assertEquals($type, $profile->emergency_contact_type);
        }
    }

    public function test_student_profile_does_not_have_old_contact_fields(): void
    {
        $user = User::factory()->create();

        $profile = StudentProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertFalse(isset($profile->parent_name));
        $this->assertFalse(isset($profile->parent_phone));
        $this->assertFalse(isset($profile->parent_email));
        $this->assertFalse(isset($profile->mentor_name));
        $this->assertFalse(isset($profile->mentor_email));
        $this->assertFalse(isset($profile->guardian_name));
        $this->assertFalse(isset($profile->guardian_phone));
    }

    public function test_emergency_contact_fields_are_fillable(): void
    {
        $fillable = (new StudentProfile())->getFillable();

        $this->assertContains('emergency_contact_name', $fillable);
        $this->assertContains('emergency_contact_phone', $fillable);
        $this->assertContains('emergency_contact_type', $fillable);
        $this->assertContains('emergency_contact_email', $fillable);

        $this->assertNotContains('parent_name', $fillable);
        $this->assertNotContains('parent_phone', $fillable);
        $this->assertNotContains('parent_email', $fillable);
        $this->assertNotContains('mentor_name', $fillable);
        $this->assertNotContains('mentor_email', $fillable);
        $this->assertNotContains('guardian_name', $fillable);
        $this->assertNotContains('guardian_phone', $fillable);
    }

    public function test_student_profile_can_be_created_with_identification_fields(): void
    {
        $user = User::factory()->create();

        $profile = StudentProfile::create([
            'user_id' => $user->id,
            'iin' => '123456789012',
            'student_id' => 'STU001',
            'faculty' => 'Engineering',
            'specialist' => 'Computer Science',
            'enrollment_year' => 2024,
            'gender' => 'male',
            'identification_type' => 'passport',
            'identification_number' => 'A123456789',
        ]);

        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $user->id,
            'identification_type' => 'passport',
            'identification_number' => 'A123456789',
        ]);

        $this->assertEquals('passport', $profile->identification_type);
        $this->assertEquals('A123456789', $profile->identification_number);
    }

    public function test_student_profile_accepts_all_identification_types(): void
    {
        $user = User::factory()->create();

        $types = [
            ['type' => 'passport', 'number' => 'A123456789'],
            ['type' => 'national_id', 'number' => '123456789012'],
            ['type' => 'drivers_license', 'number' => 'DL987654321'],
            ['type' => 'other', 'number' => 'OTHER123456']
        ];

        foreach ($types as $identification) {
            $profile = StudentProfile::create([
                'user_id' => $user->id,
                'iin' => fake()->unique()->numerify('############'),
                'student_id' => fake()->unique()->numerify('STU#####'),
                'faculty' => 'Engineering',
                'specialist' => 'Computer Science',
                'enrollment_year' => 2024,
                'gender' => 'male',
                'identification_type' => $identification['type'],
                'identification_number' => $identification['number'],
            ]);

            $this->assertEquals($identification['type'], $profile->identification_type);
            $this->assertEquals($identification['number'], $profile->identification_number);
        }
    }

    public function test_identification_fields_are_fillable(): void
    {
        $fillable = (new StudentProfile())->getFillable();

        $this->assertContains('identification_type', $fillable);
        $this->assertContains('identification_number', $fillable);
    }
}
