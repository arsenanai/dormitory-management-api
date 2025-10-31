<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\City;
use App\Models\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase {
	use RefreshDatabase;

	private $studentRoleId;

	protected function setUp(): void {
		parent::setUp();
		// Use factories instead of the full seeder to avoid data conflicts.
		Role::factory()->create(['name' => 'student']);
		$this->seed(\Database\Seeders\KazakhstanSeeder::class); // For cities
		$this->studentRoleId = Role::where('name', 'student')->firstOrFail()->id;
	}

	#[Test]
	public function student_can_register_with_valid_data(): void {
		Storage::fake( 'public' );
		$city = City::first() ?? City::factory()->create(); // Use existing city or create one
		$dormitory = \App\Models\Dormitory::factory()->create();
		$room = \App\Models\Room::factory()->create(['dormitory_id' => $dormitory->id]);
		$bed = $room->beds()->first();

		$uniqueIIN = '123456789' . str_pad( rand( 0, 999 ), 3, '0', STR_PAD_LEFT );
		$payload = [ 
			'iin'                      => $uniqueIIN,
			'name'                     => 'John Doe',
			'faculty'                  => 'engineering',
			'specialist'               => 'computer_sciences',
			'enrollment_year'          => '2022',
			'gender'                   => 'male',
			'email'                    => 'student@example.com',
			'phone_numbers'            => [ '+77001234567' ],
			'password'                 => 'password123',
			'password_confirmation'    => 'password123',
			'deal_number'              => 'D123',
			'room_id'                  => $room->id,
			'city_id'                  => $city->id,
			'files'                    => [ 
				UploadedFile::fake()->create( '063.pdf', 100 ),
				UploadedFile::fake()->create( '075.pdf', 100 ),
				UploadedFile::fake()->create( 'id.pdf', 100 ),
				UploadedFile::fake()->create( 'bank.pdf', 100 ),
			],
			'agree_to_dormitory_rules' => true,
			'user_type'                => 'student',
			'bed_id'                   => $bed->id,
		];

		$response = $this->postJson( '/api/register', $payload );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'users', [ 
			'email' => 'student@example.com',
		] );
		$this->assertDatabaseHas( 'student_profiles', [ 
			'iin' => $uniqueIIN,
		] );
		$student = User::where('email', 'student@example.com')->first();
		$this->assertNotNull($student->studentProfile->files[0]);
		Storage::disk( 'public' )->assertExists( $student->studentProfile->files[0] );
	}

	#[Test]
	public function student_can_register_with_room_id_string(): void {
		$city = City::first() ?? \App\Models\City::factory()->create(); // Use existing city
		$dormitory = \App\Models\Dormitory::factory()->create(); // Create a fresh dormitory
		$room = \App\Models\Room::factory()->create(['dormitory_id' => $dormitory->id]);
		$bed = $room->beds()->first(); // Use a bed created by the RoomFactory
		$uniqueEmail = 'janedoe_' . uniqid() . '@example.com';
		$uniqueIIN = '987654321' . str_pad( rand( 0, 999 ), 3, '0', STR_PAD_LEFT );
		$payload = [ 
			'iin'                      => $uniqueIIN,
			'name'                     => 'Jane Doe',
			'faculty'                  => 'engineering',
			'specialist'               => 'computer_sciences',
			'enrollment_year'          => 2022,
			'gender'                   => 'female',
			'email'                    => $uniqueEmail,
			'phone_numbers'            => [ '+77001234568' ],
			'room_id'                  => (string) $room->id,
			'password'                 => 'password123',
			'password_confirmation'    => 'password123',
			'deal_number'              => 'D124',
			'city_id'                  => $city->id,
			'agree_to_dormitory_rules' => true,
			'user_type'                => 'student',
			'bed_id'                   => (string) $bed->id, // Simulate string id
		];
		$response = $this->postJson( '/api/register', $payload );
		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'users', [ 
			'email'   => $uniqueEmail,
			'room_id' => $bed->room_id,
		] );
	}

	#[Test]
	public function registration_requires_required_fields(): void {
		// Create necessary related data for validation to pass on some fields
		$dormitory = \App\Models\Dormitory::factory()->create();
		$room = \App\Models\Room::factory()->create(['dormitory_id' => $dormitory->id]);

		$response = $this->postJson( '/api/register', [
			'user_type' => 'student',
			'room_id' => $room->id, // Provide a room_id to isolate the test
		] );

		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 
			'iin', 'name', 'faculty', 'specialist', 'enrollment_year',
			'gender', 'email', 'password', 'agree_to_dormitory_rules'
		] );
	}

	#[Test]
	public function registration_requires_valid_email_and_password(): void {
		$payload = [ 
			'iin'                      => '123',
			'name'                     => '',
			'faculty'                  => '',
			'specialist'               => '',
			'enrollment_year'          => 'abcd',
			'gender'                   => 'other',
			'email'                    => 'not-an-email',
			'password'                 => '123',
			'password_confirmation'    => '456',
			'agree_to_dormitory_rules' => false,
			'user_type'                => 'student',
		];

		$response = $this->postJson( '/api/register', $payload );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 
			'iin', 'name', 'faculty', 'specialist', 'enrollment_year',
			'gender', 'email', 'password', 'agree_to_dormitory_rules', 'room_id'
		] );
	}

	#[Test]
	public function student_is_not_assigned_a_bed_on_public_registration(): void
	{
		Storage::fake('public');
		$dormitory = \App\Models\Dormitory::factory()->create(); // Create a fresh dormitory
		$room = \App\Models\Room::factory()->create(['dormitory_id' => $dormitory->id]);

		$payload = [
			'iin'                      => '112233445566',
			'name'                     => 'Bedless Student',
			'faculty'                  => 'Arts',
			'specialist'               => 'History',
			'enrollment_year'          => '2023',
			'gender'                   => 'female',
			'email'                    => 'bedless.student@example.com',
			'password'                 => 'password123',
			'password_confirmation'    => 'password123',
			'room_id'                  => $room->id, // Student selects a room
			// No 'bed_id' is sent, mimicking the public registration form
			'agree_to_dormitory_rules' => true,
			'user_type'                => 'student',
		];

		$response = $this->postJson('/api/register', $payload);

		$response->assertStatus(201);
		$student = User::where('email', 'bedless.student@example.com')->first();
		$this->assertNotNull($student);
		$this->assertNull($student->studentBed, 'Student should not be assigned to a specific bed upon public registration.');
		$this->assertEquals($room->id, $student->room_id, 'Student should be associated with the selected room.');
	}

	public function student_can_register_when_agree_to_rules_is_a_string_true(): void
	{
		Storage::fake('public');
		$dormitory = \App\Models\Dormitory::factory()->create();
		$room = \App\Models\Room::factory()->create(['dormitory_id' => $dormitory->id]);
		$bed = $room->beds()->first();

		$payload = [
			'iin'                      => '998877665544',
			'name'                     => 'String Agreement',
			'faculty'                  => 'Science',
			'specialist'               => 'Biology',
			'enrollment_year'          => '2023',
			'gender'                   => 'male',
			'email'                    => 'string.agree@example.com',
			'password'                 => 'password123',
			'password_confirmation'    => 'password123',
			'room_id'                  => $room->id,
			'bed_id'                   => $bed->id,
			'agree_to_dormitory_rules' => 'true', // Sent as a string, mimicking FormData
			'user_type'                => 'student',
		];

		$response = $this->postJson('/api/register', $payload);

		$response->assertStatus(201);
		$this->assertDatabaseHas('student_profiles', [
			'iin' => '998877665544',
			'agree_to_dormitory_rules' => 1, // Should be stored as 1 (true) in the database
		]);
	}
}