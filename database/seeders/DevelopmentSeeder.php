<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Dormitory;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\Bed;
use App\Models\Country;
use App\Models\Region;
use App\Models\City;
use App\Models\SemesterPayment;
use App\Models\Message;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\AdminProfile;

class DevelopmentSeeder extends Seeder {
	public function run(): void {
		// Get admin user for message sender and payment approvals
		$adminUser = User::where( 'role_id', Role::where( 'name', 'admin' )->first()->id )->first();
		if ( ! $adminUser ) {
			$adminUser = User::where( 'role_id', Role::where( 'name', 'sudo' )->first()->id )->first();
		}
		if ( ! $adminUser ) {
			throw new \Exception( 'No admin or sudo user found. Please ensure users are seeded first.' );
		}

		// Create sample countries, regions, cities
		$kazakhstan = Country::firstOrCreate( [ 'name' => 'Kazakhstan' ] );
		$almaty_region = Region::firstOrCreate( [ 
			'name'       => 'Almaty Region',
			'country_id' => $kazakhstan->id
		] );
		$almaty_city = City::firstOrCreate( [ 
			'name'      => 'Almaty',
			'region_id' => $almaty_region->id
		] );

		// Create roles
		$adminRole = \App\Models\Role::firstOrCreate( [ 'name' => 'admin' ] );
		$sudoRole = \App\Models\Role::firstOrCreate( [ 'name' => 'sudo' ] );
		$studentRole = \App\Models\Role::firstOrCreate( [ 'name' => 'student' ] );

		// Get existing room types (created by RoomTypeSeeder)
		$standardRoomType = RoomType::where( 'name', 'standard' )->first();
		$luxRoomType = RoomType::where( 'name', 'lux' )->first();

		// Ensure room types exist
		if ( ! $standardRoomType || ! $luxRoomType ) {
			throw new \Exception( 'Room types not found. Please run RoomTypeSeeder first.' );
		}

		// Create dormitories
		$dormitory1 = Dormitory::firstOrCreate( [ 
			'name'        => 'Dormitory #1',
			'address'     => 'Almaty, Al-Farabi Avenue, 71',
			'description' => 'Main student dormitory',
			'gender'      => 'mixed',
			'capacity'    => 200,
		] );
		// Assign admin to dormitory #1 for moderation context
		if ( empty( $dormitory1->admin_id ) ) {
			$dormitory1->admin_id = $adminUser->id;
			$dormitory1->save();
		}

		$dormitory2 = Dormitory::firstOrCreate( [ 
			'name'        => 'Dormitory #2',
			'address'     => 'Almaty, Nazarbayev Avenue, 123',
			'description' => 'Second student dormitory',
			'gender'      => 'male',
			'capacity'    => 200,
		] );

		// Create test dormitory for E2E tests
		$testDormitory = Dormitory::firstOrCreate( [ 
			'name'        => 'A Block',
			'address'     => 'Test Address for E2E',
			'description' => 'Test dormitory for E2E tests',
			'gender'      => 'mixed',
			'capacity'    => 50,
		] );

		// Create rooms for dormitory 1
		for ( $floor = 1; $floor <= 5; $floor++ ) {
			for ( $roomNum = 1; $roomNum <= 10; $roomNum++ ) {
				$roomNumber = $floor . str_pad( $roomNum, 2, '0', STR_PAD_LEFT );

				$room = Room::firstOrCreate( [ 
					'number'       => $roomNumber,
					'dormitory_id' => $dormitory1->id,
					'room_type_id' => $roomNum <= 8 ? $standardRoomType->id : $luxRoomType->id,
					'floor'        => $floor,
					'quota'        => $roomNum <= 8 ? 2 : 1, // Set quota based on room type
				] );

				// Create beds for this room
				$bedCount = $roomNum <= 8 ? 2 : 1;
				for ( $bedNum = 1; $bedNum <= $bedCount; $bedNum++ ) {
					Bed::firstOrCreate( [ 
						'bed_number' => $bedNum,
						'room_id'    => $room->id,
					] );
				}
			}
		}

		// Create test rooms for E2E tests
		$testRoom = Room::firstOrCreate( [ 
			'number'       => 'a210',
			'dormitory_id' => $testDormitory->id,
			'room_type_id' => $standardRoomType->id,
			'floor'        => 2,
			'quota'        => 2, // Standard room has 2 beds
		] );

		// Create test beds for E2E tests
		Bed::firstOrCreate( [ 
			'bed_number' => 1,
			'room_id'    => $testRoom->id,
		] );

		Bed::firstOrCreate( [ 
			'bed_number' => 2,
			'room_id'    => $testRoom->id,
		] );

		// Create sample students - UPDATED FOR E2E TESTS
		$students = [ 
			[ 
				'name'     => 'Test Student',
				'email'    => env( 'STUDENT_EMAIL', 'student@email.com' ),
				'password' => env( 'STUDENT_PASSWORD', 'studentpass' ),
				// ...student-specific fields removed, handled in StudentProfile...
			],
			[ 
				'name'     => 'Alice Student',
				'email'    => 'alice@student.local',
				'password' => 'password',
				// ...student-specific fields removed, handled in StudentProfile...
			],
			[ 
				'name'     => 'Bob Student',
				'email'    => 'bob@student.local',
				'password' => 'password',
				// ...student-specific fields removed, handled in StudentProfile...
			],
		];

		foreach ( $students as $index => $studentData ) {
			$room = Room::where( 'dormitory_id', $dormitory1->id )->skip( $index )->first();
			$bed = $room->beds()->whereNull( 'user_id' )->first();
			$student = User::firstOrCreate(
				[ 'email' => $studentData['email'] ],
				[ 
					'name'          => $studentData['name'],
					'email'         => $studentData['email'],
					'password'      => Hash::make( $studentData['password'] ?? 'password' ),
					'role_id'       => $studentRole->id,
					'status'        => 'active',
					'room_id'       => $room->id,
					'first_name'    => $studentData['name'],
					'last_name'     => 'Student',
					'phone_numbers' => json_encode( [ '+77001234567' ] ),
				]
			);
			// Create StudentProfile for student-specific fields
			\App\Models\StudentProfile::firstOrCreate(
				[ 
					'user_id'    => $student->id,
					'student_id' => 'STU' . str_pad( $student->id, 5, '0', STR_PAD_LEFT ),
					'iin'        => '1234567890' . str_pad( $student->id, 2, '0', STR_PAD_LEFT ),
				],
				[ 
					'gender'                         => $studentData['gender'] ?? 'male',
					'faculty'                        => $studentData['faculty'] ?? 'Engineering',
					'specialist'                     => $studentData['specialist'] ?? 'Software Engineering',
					'course'                         => 1,
					'year_of_study'                  => 1,
					'enrollment_year'                => 2024,
					'enrollment_date'                => now()->subYears( 1 ),
					'blood_type'                     => 'O+',
					'parent_name'                    => 'Parent ' . $studentData['name'],
					'parent_phone'                   => '+77012345678',
					'parent_email'                   => 'parent_' . $studentData['email'],
					'guardian_name'                  => 'Guardian ' . $studentData['name'],
					'guardian_phone'                 => '+77012345679',
					'mentor_name'                    => 'Mentor ' . $studentData['name'],
					'mentor_email'                   => 'mentor_' . $studentData['email'],
					'emergency_contact_name'         => 'Emergency ' . $studentData['name'],
					'emergency_contact_phone'        => '+77012345680',
					'emergency_contact_relationship' => 'Father',
					'medical_conditions'             => null,
					'dietary_restrictions'           => null,
					'program'                        => 'Computer Science',
					'year_level'                     => 1,
					'nationality'                    => 'Kazakh',
					'deal_number'                    => 'DEAL' . str_pad( $student->id, 5, '0', STR_PAD_LEFT ),
					'agree_to_dormitory_rules'       => true,
					'has_meal_plan'                  => true,
					'registration_limit_reached'     => false,
					'is_backup_list'                 => false,
					'date_of_birth'                  => now()->subYears( 18 ),
					'files'                          => json_encode( [] ),
					'city_id'                        => $almaty_city->id,
					'country'                        => 'Kazakhstan',
					'region'                         => 'Almaty Region',
					'city'                           => 'Almaty',
					'medical_conditions'             => 'None',
					'violations'                     => 'None',
				]
			);

			// Assign bed to student
			if ( $bed ) {
				$bed->update( [ 'user_id' => $student->id ] );
			}

			// Create sample payments
			SemesterPayment::firstOrCreate(
				[ 
					'user_id'  => $student->id,
					'semester' => '2025-fall',
				],
				[ 
					'year'                      => 2025,
					'semester_type'             => 'fall',
					'amount'                    => 50000 + ( $index * 5000 ),
					'payment_approved'          => true,
					'dormitory_access_approved' => true,
					'payment_approved_at'       => now()->subDays( 10 ),
					'dormitory_approved_at'     => now()->subDays( 5 ),
					'payment_approved_by'       => $adminUser ? $adminUser->id : null,
					'dormitory_approved_by'     => $adminUser ? $adminUser->id : null,
					'due_date'                  => now()->addDays( 30 ),
					'paid_date'                 => now(),
					'payment_notes'             => 'Paid in full',
					'dormitory_notes'           => 'Access granted',
					'payment_status'            => 'approved',
					'dormitory_status'          => 'approved',
					'receipt_file'              => null,
				]
			);

			// Extra payments for pagination testing across multiple semesters to avoid unique constraint
			$semesterPool = [ '2025-spring', '2025-summer', '2025-fall', '2024-fall', '2024-spring', '2024-summer' ];
			for ( $i = 1; $i <= 25; $i++ ) {
				$sem = $semesterPool[ ( $i - 1 ) % count( $semesterPool ) ];
				list( $yr, $semType ) = explode( '-', $sem );
				SemesterPayment::firstOrCreate(
					[ 
						'user_id'  => $student->id,
						'semester' => $sem,
					],
					[ 
						'year'            => (int) $yr,
						'semester_type'   => $semType,
						'amount'          => 1000 + $i,
						'payment_status'  => 'approved',
						'payment_method'  => 'bank_transfer',
						'contract_number' => 'SEED-' . $student->id . '-' . $i,
						'contract_date'   => now()->subDays( $i ),
						'payment_date'    => now()->subDays( $i ),
						'due_date'        => now()->addDays( 30 ),
					]
				);
			}
		}

		// Seed additional students with payments in admin's dormitory for pagination testing
		for ( $j = 1; $j <= 100; $j++ ) {
			$email = 'seed' . $j . '@student.local';
			$existing = User::where( 'email', $email )->first();
			if ( ! $existing ) {
				// Find a room with a free bed in dormitory #1
				$roomWithFreeBed = Room::where( 'dormitory_id', $dormitory1->id )
					->whereHas( 'beds', function ($q) {
						$q->whereNull( 'user_id' );
					} )
					->with( 'beds' )
					->first();
				if ( ! $roomWithFreeBed ) {
					break;
				}
				$student = User::updateOrCreate(
					[ 'email' => $email ],
					[ 
						'name'          => 'Seed Student ' . $j,
						'email'         => $email,
						'password'      => Hash::make( 'password' ),
						'role_id'       => $studentRole->id,
						'room_id'       => $roomWithFreeBed->id,
						'first_name'    => 'Seed',
						'last_name'     => 'Student',
						'phone_numbers' => json_encode( [ '+7700000000' . $j ] ),
						'status'        => 'active',
					]
				);
				// occupy a bed
				$bed = $roomWithFreeBed->beds()->whereNull( 'user_id' )->first();
				if ( $bed ) {
					$bed->update( [ 'user_id' => $student->id ] );
				}
				// Profile
				\App\Models\StudentProfile::firstOrCreate(
					[ 'user_id' => $student->id ],
					[ 
						'iin'             => '9900' . str_pad( $student->id, 8, '0', STR_PAD_LEFT ),
						'student_id'      => 'STU' . str_pad( $student->id, 5, '0', STR_PAD_LEFT ),
						'faculty'         => 'Engineering',
						'specialist'      => 'Software Engineering',
						'enrollment_year' => 2024,
					]
				);
				// Payment for current semester for this student
				SemesterPayment::firstOrCreate(
					[ 'user_id' => $student->id, 'semester' => '2025-fall' ],
					[ 
						'year'            => 2025,
						'semester_type'   => 'fall',
						'amount'          => 10000 + $j,
						'payment_status'  => 'approved',
						'payment_method'  => 'bank_transfer',
						'contract_number' => 'SEED-NEW-' . $j,
						'contract_date'   => now()->subDays( $j ),
						'payment_date'    => now()->subDays( $j ),
						'due_date'        => now()->addDays( 30 ),
					]
				);
			}
		}

		// Create sample messages for pagination testing
		$messageTitles = [
			'Welcome to Dormitory',
			'Floor Meeting',
			'Maintenance Notice',
			'Security Update',
			'WiFi Password Change',
			'Laundry Room Schedule',
			'Kitchen Rules Reminder',
			'Quiet Hours Policy',
			'Emergency Contact Info',
			'Parking Regulations',
			'Guest Policy Update',
			'Room Inspection Notice',
			'Fire Safety Reminder',
			'Water Conservation',
			'Electricity Usage Alert',
			'Internet Maintenance',
			'Cleaning Schedule',
			'Recreation Room Hours',
			'Study Room Booking',
			'Library Access Info',
			'Transportation Schedule',
			'Medical Services Info',
			'Counseling Services',
			'Career Fair Notice',
			'Academic Support',
			'Social Events Calendar',
			'Sports Tournament',
			'Cultural Festival',
			'Student Elections',
			'Volunteer Opportunities',
			'Scholarship Information',
			'Internship Programs',
			'Job Fair Announcement',
			'Graduation Ceremony',
			'Alumni Meeting',
			'Research Opportunities',
			'Conference Registration',
			'Workshop Schedule',
			'Seminar Announcement',
			'Training Program',
			'Certification Course',
			'Language Classes',
			'Music Lessons',
			'Art Workshop',
			'Photography Club',
			'Chess Tournament',
			'Book Club Meeting',
			'Movie Night',
			'Game Tournament',
			'Karaoke Night',
			'Dance Competition',
			'Poetry Reading',
			'Open Mic Night'
		];

		$recipientTypes = ['all', 'dormitory', 'room'];
		$messageTypes = ['general', 'announcement', 'violation', 'emergency', 'urgent'];

		// Create 50 messages for pagination testing
		for ($i = 0; $i < 50; $i++) {
			$recipientType = $recipientTypes[array_rand($recipientTypes)];
			$messageType = $messageTypes[array_rand($messageTypes)];
			$title = $messageTitles[array_rand($messageTitles)] . ' #' . ($i + 1);
			
			$messageData = [
				'sender_id'      => $adminUser->id,
				'title'          => $title,
				'content'        => 'This is message content for ' . $title . '. Please read carefully and follow the instructions.',
				'recipient_type' => $recipientType,
				'type'           => $messageType,
				'status'         => 'sent',
				'sent_at'        => now()->subDays(rand(1, 30)),
			];

			// Add dormitory_id for dormitory messages
			if ($recipientType === 'dormitory') {
				$messageData['dormitory_id'] = $dormitory1->id;
			}

			// Add room_id for room messages (occasionally)
			if ($recipientType === 'room' && rand(1, 3) === 1) {
				$messageData['room_id'] = $room->id;
			}

			Message::firstOrCreate(
				['title' => $title], // Use title as unique identifier
				$messageData
			);
		}

		// Create sample guest data for E2E tests
		$guestUser = User::firstOrCreate(
			[ 'email' => env( 'GUEST_EMAIL', 'guest@test.local' ) ],
			[ 
				'name'     => 'Test Guest',
				'email'    => env( 'GUEST_EMAIL', 'guest@test.local' ),
				'password' => Hash::make( env( 'GUEST_PASSWORD', 'password' ) ),
				'role_id'  => Role::where( 'name', 'guest' )->firstOrCreate( [ 'name' => 'guest' ] )->id,
				'status'   => 'active',
			]
		);

		// Create guest profile
		\App\Models\GuestProfile::firstOrCreate(
			[ 'user_id' => $guestUser->id ],
			[ 
				'purpose_of_visit'        => 'Academic Visit',
				'host_name'               => 'Dr. Smith',
				'host_contact'            => '+77001234567',
				'daily_rate'              => 5000,
				'visit_start_date'        => now()->subDays( 2 ),
				'visit_end_date'          => now()->addDays( 5 ),
				'identification_type'     => 'passport',
				'identification_number'   => 'AB1234567',
				'emergency_contact_name'  => 'Emergency Contact',
				'emergency_contact_phone' => '+77001234568',
				'is_approved'             => true,
			]
		);

		// Note: Guest payments are handled differently in this system
		// Guest payments are tracked through the GuestProfile model

		// Create a default dormitory and room for foreign key references in tests
		$dormitory = \App\Models\Dormitory::firstOrCreate( [ 
			'name'     => 'Default Dormitory',
			'capacity' => 100
		] );
		$room = \App\Models\Room::firstOrCreate( [ 
			'number'       => 'A101',
			'dormitory_id' => $dormitory->id,
			'room_type_id' => $standardRoomType->id,
			'floor'        => 1
		] );

		$this->command->info( 'Development data seeded successfully!' );
	}
}
