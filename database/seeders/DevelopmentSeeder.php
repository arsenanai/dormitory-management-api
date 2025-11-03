<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Dormitory;
use App\Models\RoomType;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\AdminProfile;
use App\Models\Message;
use App\Models\SemesterPayment;

class DevelopmentSeeder extends Seeder {
	public function run(): void {
		// Create roles
		$adminRole = \App\Models\Role::firstOrCreate( [ 'name' => 'admin' ] );
		$studentRole = \App\Models\Role::firstOrCreate( [ 'name' => 'student' ] );
		$guestRole = \App\Models\Role::firstOrCreate(['name' => 'guest']);

		// Create Room Types
		$standardRoomType = RoomType::firstOrCreate( [ 'name' => 'standard' ], [ 'capacity' => 2, 'price' => 150.00 ] );
		$luxRoomType = RoomType::firstOrCreate( [ 'name' => 'lux' ], [ 'capacity' => 1, 'price' => 300.00 ] );

		// Create Blood Types
		$bloodTypes = [ 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-' ];
		foreach ( $bloodTypes as $type ) {
			\App\Models\BloodType::firstOrCreate( [ 'name' => $type ] );
		}

		// Find or create the main admin user
		$adminUser = User::firstOrCreate(
			[ 'email' => env( 'ADMIN_EMAIL', 'admin@email.com' ) ],
			[
				'name' => 'Main Admin',
				'password' => Hash::make( env( 'ADMIN_PASSWORD', 'supersecret' ) ),
				'role_id' => $adminRole->id,
				'status' => 'active',
			]
		);

		// Create and assign the main dormitory for the admin
		$adminDormitory = Dormitory::firstOrCreate(
			[ 'name' => 'Main Dormitory' ],
			[
				'address' => '123 Developer Lane',
				'gender' => 'male',
				'capacity' => 500,
				'admin_id' => $adminUser->id, // Assign admin directly to the dormitory
			]
		);

		// Assign dormitory to admin via AdminProfile
		// This is for profile-specific info, while dormitory->admin_id is the main link.
		AdminProfile::updateOrCreate(
			[ 'user_id' => $adminUser->id ],
			[ 'dormitory_id' => $adminDormitory->id ]
		);

		// Create 50 rooms in the admin's dormitory
		for ( $i = 1; $i <= 50; $i++ ) {
			$floor = ceil( $i / 10 );
			$roomNum = $i % 10 === 0 ? 10 : $i % 10;
			$roomType = ( $i % 5 === 0 ) ? $luxRoomType : $standardRoomType; // Mix in some lux rooms

			$room = Room::create( [
				'number' => $floor . str_pad( $roomNum, 2, '0', STR_PAD_LEFT ),
				'dormitory_id' => $adminDormitory->id,
				'room_type_id' => $roomType->id,
				'floor' => $floor,
			] );

			// Create beds for the room based on capacity
			for ( $j = 1; $j <= $roomType->capacity; $j++ ) {
				\App\Models\Bed::create( [
					'bed_number' => $j,
					'room_id' => $room->id,
				] );
			}
		}

		// Get all available beds to assign to students
		$availableBeds = \App\Models\Bed::whereHas( 'room', function ($q) use ($adminDormitory) {
			$q->where( 'dormitory_id', $adminDormitory->id );
		} )->whereNull( 'user_id' )->get();

		$bedIterator = 0;

		$faker = \Faker\Factory::create();

		// Create 500 students
		for ( $i = 1; $i <= 500; $i++ ) {
			$firstName = $faker->firstName;
			$lastName = $faker->lastName;
			$gender = 'male';

			// Assign a bed if available
			$bed = $availableBeds[ $bedIterator ] ?? null;
			if ( $bed ) {
				$bedIterator++;
			}

			// Create User
			$student = User::create( [
				'name' => "$firstName $lastName",
				'first_name' => $firstName,
				'last_name' => $lastName,
				'email' => $faker->unique()->safeEmail,
				'password' => Hash::make( 'password' ),
				'role_id' => $studentRole->id,
				'status' => 'active',
				'dormitory_id' => $bed ? $bed->room->dormitory_id : null,
				'room_id' => $bed ? $bed->room_id : null,
			] );

			// Assign bed to student
			if ( $bed ) {
				$bed->user_id = $student->id;
				$bed->is_occupied = true;
				$bed->save();
			}

			// Generate fake files
			$filePaths = [];
			$base64Images = [
				'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/wcAAwAB/epv2AAAAABJRU5ErkJggg==',
				'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
				'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
				'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwAB/epv2AAAAABJRU5ErkJggg==',
			];
			foreach ( $base64Images as $key => $base64Image ) {
				$filename = 'student_' . $student->id . '_doc_' . ( $key + 1 ) . '.png';
				$filePaths[] = $this->storeBase64Image( $base64Image, 'student_documents', $filename );
			}

			// Create Student Profile with all fields
			\App\Models\StudentProfile::create( [
				'user_id' => $student->id,
				'iin' => $faker->unique()->numerify( '############' ),
				'student_id' => 'STU' . str_pad( $student->id, 6, '0', STR_PAD_LEFT ),
				'faculty' => $faker->randomElement( [ 'Engineering', 'Business', 'Medicine', 'Law', 'Arts' ] ),
				'specialist' => $faker->randomElement( [ 'Computer Science', 'Marketing', 'General Medicine' ] ),
				'enrollment_year' => $faker->numberBetween( 2020, 2024 ),
				'gender' => $gender,
				'blood_type' => $faker->randomElement( $bloodTypes ),
				'country' => $faker->country,
				'region' => $faker->state,
				'city' => $faker->city,
				'parent_name' => $faker->name( $gender === 'male' ? 'male' : 'female' ),
				'parent_phone' => $faker->phoneNumber,
				'parent_email' => $faker->safeEmail,
				'emergency_contact_name' => $faker->name,
				'emergency_contact_phone' => $faker->phoneNumber,
				'deal_number' => 'DEAL-' . $faker->numerify( '######' ),
				'agree_to_dormitory_rules' => true,
				'has_meal_plan' => $faker->boolean,
				'date_of_birth' => $faker->dateTimeBetween( '-25 years', '-18 years' ),
				'allergies' => $faker->optional( 0.2 )->sentence,
				'violations' => $faker->optional( 0.1 )->sentence,
				'files' => $filePaths,
			] );
		}

		// Create 10 Guests
		$guestRooms = Room::where('dormitory_id', $adminDormitory->id)->inRandomOrder()->take(10)->get();
		foreach ($guestRooms as $guestRoom) {
			if ($guestRoom) {
				$guestUser = User::create([
					'name' => $faker->name,
					'first_name' => $faker->firstName,
					'last_name' => $faker->lastName,
					'email' => $faker->unique()->safeEmail,
					'password' => Hash::make('password'),
					'role_id' => $guestRole->id,
					'status' => 'active',
					'dormitory_id' => $adminDormitory->id,
					'room_id' => $guestRoom->id,
				]);
	
				\App\Models\GuestProfile::create([
					'user_id' => $guestUser->id,
					'purpose_of_visit' => $faker->sentence,
					'visit_start_date' => now()->subDays(rand(1, 5)),
					'visit_end_date' => now()->addDays(rand(2, 10)),
					'is_approved' => true,
				]);
			}
		}

		// Create 10 Messages from Admin
		for ($i = 0; $i < 10; $i++) {
			Message::create([
				'sender_id' => $adminUser->id,
				'receiver_id' => null,
				'recipient_type' => 'dormitory',
				'dormitory_id' => $adminDormitory->id,
				'title' => $faker->bs,
				'content' => $faker->realText(200),
				'type' => 'announcement',
				'status' => 'sent',
				'sent_at' => now(),
			]);
		}

		// Create Semester Payments for half of the students
		$studentsForPayment = User::where('role_id', $studentRole->id)->take(250)->get();
		$currentSemester = SemesterPayment::getCurrentSemester();
		list($year, $semesterType) = explode('-', $currentSemester);

		foreach ($studentsForPayment as $student) {
			SemesterPayment::create([
				'user_id' => $student->id,
				'semester' => $currentSemester,
				'year' => $year,
				'semester_type' => $semesterType,
				'due_date' => now()->addDays(30),
				'amount' => $faker->randomFloat(2, 50000, 150000),
				'payment_method' => 'kaspi',
				'payment_status' => 'approved',
				'payment_approved' => true,
				'dormitory_access_approved' => true,
				'payment_date' => now()->subDays(rand(1, 30)),
			]);
		}

		$this->command->info( 'Development data seeded successfully!' );
	}

	/**
	 * Decodes a base64 string and stores it as a file in the public disk.
	 *
	 * @param string $base64String The base64 encoded image data.
	 * @param string $directory The directory within storage/app/public to save the file.
	 * @param string $filename The desired filename.
	 * @return string The path to the stored file.
	 */
	private function storeBase64Image(string $base64String, string $directory, string $filename): string
	{
		$imageData = base64_decode($base64String);
		$path = $directory . '/' . $filename;
		Storage::disk('public')->put($path, $imageData);
		return $path;
	}
}
