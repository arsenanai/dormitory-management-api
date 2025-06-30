<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase {
	use RefreshDatabase;

	private $studentRoleId;

	protected function setUp(): void {
		parent::setUp();
		$this->seed();
		$this->studentRoleId = Role::where( 'name', 'student' )->firstOrFail()->id;
	}

	public function test_student_can_register_with_valid_data() {
		Storage::fake( 'public' );
		$city = City::factory()->create();
		$payload = [ 
			'iin'                      => '123456789012',
			'name'                     => 'John Doe',
			'faculty'                  => 'engineering',
			'specialist'               => 'computer_sciences',
			'enrollment_year'          => 2022,
			'gender'                   => 'male',
			'email'                    => 'student@example.com',
			'phone_numbers'            => [ '+77001234567' ],
			'room_id'                  => null,
			'password'                 => 'password123',
			'password_confirmation'    => 'password123',
			'deal_number'              => 'D123',
			'city_id'                  => $city->id,
			'files'                    => [ 
				UploadedFile::fake()->create( '063.pdf', 100 ),
				UploadedFile::fake()->create( '075.pdf', 100 ),
				UploadedFile::fake()->create( 'id.pdf', 100 ),
				UploadedFile::fake()->create( 'bank.pdf', 100 ),
			],
			'agree_to_dormitory_rules' => true,
			'role_id'                  => $this->studentRoleId,
		];

		$response = $this->postJson( '/api/register', $payload );

		$response->assertStatus( 201 );
		$this->assertDatabaseHas( 'users', [ 
			'email' => 'student@example.com',
			'iin'   => '123456789012',
		] );
		Storage::disk( 'public' )->assertExists( 'user_files/' . $payload['files'][0]->hashName() );
	}

	public function test_registration_requires_required_fields() {
		$response = $this->postJson( '/api/register', [] );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 
			'iin', 'name', 'faculty', 'specialist', 'enrollment_year', 'gender',
			'email', 'password', 'agree_to_dormitory_rules'
		] );
	}

	public function test_registration_requires_valid_email_and_password() {
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
		];

		$response = $this->postJson( '/api/register', $payload );
		$response->assertStatus( 422 );
		$response->assertJsonValidationErrors( [ 
			'iin', 'name', 'faculty', 'specialist', 'enrollment_year', 'gender',
			'email', 'password', 'agree_to_dormitory_rules'
		] );
	}
}