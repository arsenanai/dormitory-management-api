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

class DevelopmentSeeder extends Seeder {
	public function run(): void {
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

		// Create roles if they don't exist
		$sudoRole = Role::firstOrCreate( [ 'name' => 'sudo' ] );
		$adminRole = Role::firstOrCreate( [ 'name' => 'admin' ] );
		$studentRole = Role::firstOrCreate( [ 'name' => 'student' ] );

		// Create sudo user
		$sudo = User::firstOrCreate(
			[ 'email' => 'sudo@dormitory.local' ],
			[ 
				'name'     => 'Super Administrator',
				'email'    => 'sudo@dormitory.local',
				'password' => Hash::make( 'password' ),
				'role_id'  => $sudoRole->id,
				'status'   => 'active',
			]
		);

		// Create room types
		$standardRoomType = RoomType::firstOrCreate(
			[ 'name' => 'standard' ],
			[ 'beds' => [ 
				[ 'id' => 1, 'x' => 50, 'y' => 50, 'occupied' => false ],
				[ 'id' => 2, 'x' => 150, 'y' => 50, 'occupied' => false ],
			] ]
		);

		$luxRoomType = RoomType::firstOrCreate(
			[ 'name' => 'lux' ],
			[ 'beds' => [ 
				[ 'id' => 1, 'x' => 100, 'y' => 100, 'occupied' => false ],
			] ]
		);

		// Create dormitories
		$dormitory1 = Dormitory::firstOrCreate( [ 
			'name'        => 'Dormitory #1',
			'address'     => 'Almaty, Al-Farabi Avenue, 71',
			'description' => 'Main student dormitory',
			'gender'      => 'mixed',
			'quota'       => 200,
			'capacity'    => 200,
		] );

		$dormitory2 = Dormitory::firstOrCreate( [ 
			'name'        => 'Dormitory #2',
			'address'     => 'Almaty, Nazarbayev Avenue, 123',
			'description' => 'Second student dormitory',
			'gender'      => 'male',
			'quota'       => 150,
			'capacity'    => 200,
		] );

		// Create admin users
		$admin1 = User::firstOrCreate(
			[ 'email' => 'admin1@dormitory.local' ],
			[ 
				'name'     => 'John Admin',
				'email'    => 'admin1@dormitory.local',
				'password' => Hash::make( 'password' ),
				'role_id'  => $adminRole->id,
				'status'   => 'active',
			]
		);

		$admin2 = User::firstOrCreate(
			[ 'email' => 'admin2@dormitory.local' ],
			[ 
				'name'     => 'Jane Admin',
				'email'    => 'admin2@dormitory.local',
				'password' => Hash::make( 'password' ),
				'role_id'  => $adminRole->id,
				'status'   => 'active',
			]
		);

		// Assign admins to dormitories
		$dormitory1->update( [ 'admin_id' => $admin1->id ] );
		$dormitory2->update( [ 'admin_id' => $admin2->id ] );

		// Create rooms for dormitory 1
		for ( $floor = 1; $floor <= 5; $floor++ ) {
			for ( $roomNum = 1; $roomNum <= 10; $roomNum++ ) {
				$roomNumber = $floor . str_pad( $roomNum, 2, '0', STR_PAD_LEFT );

				$room = Room::firstOrCreate( [ 
					'number'       => $roomNumber,
					'dormitory_id' => $dormitory1->id,
					'room_type_id' => $roomNum <= 8 ? $standardRoomType->id : $luxRoomType->id,
					'floor'        => $floor,
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

		// Create sample students
		$students = [ 
			[ 
				'name'  => 'Alice Student',
				'email' => 'alice@student.local',
				// ...student-specific fields removed, handled in StudentProfile...
			],
			[ 
				'name'  => 'Bob Student',
				'email' => 'bob@student.local',
				// ...student-specific fields removed, handled in StudentProfile...
			],
			[ 
				'name'  => 'Charlie Student',
				'email' => 'charlie@student.local',
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
					'password'      => Hash::make('password'),
					'role_id'       => $studentRole->id,
					'status'        => 'active',
					'room_id'       => $room->id,
					'first_name'    => $studentData['name'],
					'last_name'     => 'Student',
					'phone_numbers' => json_encode(['+77001234567']),
				]
			);
			// Create StudentProfile for student-specific fields
			\App\Models\StudentProfile::firstOrCreate(
				[
					'user_id'    => $student->id,
					'student_id' => 'STU' . str_pad($student->id, 5, '0', STR_PAD_LEFT),
					'iin'        => '1234567890' . str_pad($student->id, 2, '0', STR_PAD_LEFT),
				],
				[
					'gender'                         => $studentData['gender'] ?? 'male',
					'faculty'                        => $studentData['faculty'] ?? 'Engineering',
					'specialist'                     => $studentData['specialist'] ?? 'Software Engineering',
					'course'                         => 1,
					'year_of_study'                  => 1,
					'enrollment_year'                => '2024',
					'enrollment_date'                => now()->subYears(1),
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
					'deal_number'                    => 'DEAL' . str_pad($student->id, 5, '0', STR_PAD_LEFT),
					'agree_to_dormitory_rules'       => true,
					'has_meal_plan'                  => true,
					'registration_limit_reached'     => false,
					'is_backup_list'                 => false,
					'date_of_birth'                  => now()->subYears(18),
					'files'                          => json_encode([]),
					'city_id'                        => $almaty_city->id,
				]
			);

			// Assign bed to student
			if ( $bed ) {
				$bed->update( [ 'user_id' => $student->id ] );
			}

			// Create sample payments
			SemesterPayment::firstOrCreate(
				[
					'user_id'        => $student->id,
					'semester'       => '2025-fall',
				],
				[
					'year'                     => 2025,
					'semester_type'            => 'fall',
					'amount'                   => 50000 + ($index * 5000),
					'payment_approved'         => true,
					'dormitory_access_approved'=> true,
					'payment_approved_at'      => now()->subDays(10),
					'dormitory_approved_at'    => now()->subDays(5),
					'payment_approved_by'      => $admin1->id,
					'dormitory_approved_by'    => $admin1->id,
					'due_date'                 => now()->addDays(30),
					'paid_date'                => now(),
					'payment_notes'            => 'Paid in full',
					'dormitory_notes'          => 'Access granted',
					'payment_status'           => 'approved',
					'dormitory_status'         => 'approved',
					'receipt_file'             => null,
				]
			);
		}

		// Create sample messages
		Message::firstOrCreate( [ 
			'sender_id'      => $admin1->id,
			'title'          => 'Welcome to Dormitory',
			'content'        => 'Welcome to our dormitory! Please read the rules and regulations.',
			'recipient_type' => 'all',
			'status'         => 'sent',
			'sent_at'        => now()->subDays( 5 ),
		] );

		Message::firstOrCreate( [ 
			'sender_id'      => $admin1->id,
			'title'          => 'Floor Meeting',
			'content'        => 'There will be a floor meeting tomorrow at 7 PM.',
			'recipient_type' => 'dormitory',
			'dormitory_id'   => $dormitory1->id,
			'status'         => 'sent',
			'sent_at'        => now()->subDays( 2 ),
		] );

		$this->command->info( 'Development data seeded successfully!' );
	}
}
