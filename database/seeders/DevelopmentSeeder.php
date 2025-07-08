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
use App\Models\Payment;
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
				'name'            => 'Super Administrator',
				'email'           => 'sudo@dormitory.local',
				'password'        => Hash::make( 'password' ),
				'role_id'         => $sudoRole->id,
				'iin'             => '123456789012',
				'faculty'         => 'Administration',
				'specialist'      => 'System Admin',
				'enrollment_year' => 2024,
				'gender'          => 'male',
				'status'          => 'active',
				'city_id'         => $almaty_city->id,
			]
		);

		// Create room types
		$standardRoomType = RoomType::firstOrCreate( [ 
			'name' => 'standard',
			'beds' => [ 
				[ 'id' => 1, 'x' => 50, 'y' => 50, 'occupied' => false ],
				[ 'id' => 2, 'x' => 150, 'y' => 50, 'occupied' => false ],
			]
		] );

		$luxRoomType = RoomType::firstOrCreate( [ 
			'name' => 'lux',
			'beds' => [ 
				[ 'id' => 1, 'x' => 100, 'y' => 100, 'occupied' => false ],
			]
		] );

		// Create dormitories
		$dormitory1 = Dormitory::firstOrCreate( [ 
			'name'        => 'Dormitory #1',
			'address'     => 'Almaty, Al-Farabi Avenue, 71',
			'description' => 'Main student dormitory',
			'gender'      => 'mixed',
			'quota'       => 200,
		] );

		$dormitory2 = Dormitory::firstOrCreate( [ 
			'name'        => 'Dormitory #2',
			'address'     => 'Almaty, Nazarbayev Avenue, 123',
			'description' => 'Second student dormitory',
			'gender'      => 'male',
			'quota'       => 150,
		] );

		// Create admin users
		$admin1 = User::firstOrCreate(
			[ 'email' => 'admin1@dormitory.local' ],
			[ 
				'name'            => 'John Admin',
				'email'           => 'admin1@dormitory.local',
				'password'        => Hash::make( 'password' ),
				'role_id'         => $adminRole->id,
				'iin'             => '123456789013',
				'faculty'         => 'Administration',
				'specialist'      => 'Dormitory Admin',
				'enrollment_year' => 2024,
				'gender'          => 'male',
				'status'          => 'active',
				'city_id'         => $almaty_city->id,
			]
		);

		$admin2 = User::firstOrCreate(
			[ 'email' => 'admin2@dormitory.local' ],
			[ 
				'name'            => 'Jane Admin',
				'email'           => 'admin2@dormitory.local',
				'password'        => Hash::make( 'password' ),
				'role_id'         => $adminRole->id,
				'iin'             => '123456789014',
				'faculty'         => 'Administration',
				'specialist'      => 'Dormitory Admin',
				'enrollment_year' => 2024,
				'gender'          => 'female',
				'status'          => 'active',
				'city_id'         => $almaty_city->id,
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
						'number'  => $bedNum,
						'room_id' => $room->id,
					] );
				}
			}
		}

		// Create sample students
		$students = [ 
			[ 
				'name'       => 'Alice Student',
				'email'      => 'alice@student.local',
				'iin'        => '123456789015',
				'faculty'    => 'Computer Science',
				'specialist' => 'Software Engineering',
				'gender'     => 'female',
			],
			[ 
				'name'       => 'Bob Student',
				'email'      => 'bob@student.local',
				'iin'        => '123456789016',
				'faculty'    => 'Business',
				'specialist' => 'Marketing',
				'gender'     => 'male',
			],
			[ 
				'name'       => 'Charlie Student',
				'email'      => 'charlie@student.local',
				'iin'        => '123456789017',
				'faculty'    => 'Engineering',
				'specialist' => 'Mechanical Engineering',
				'gender'     => 'male',
			],
		];

		foreach ( $students as $index => $studentData ) {
			$room = Room::where( 'dormitory_id', $dormitory1->id )->skip( $index )->first();
			$bed = $room->beds()->whereNull( 'user_id' )->first();

			$student = User::firstOrCreate(
				[ 'email' => $studentData['email'] ],
				array_merge( $studentData, [ 
					'password'        => Hash::make( 'password' ),
					'role_id'         => $studentRole->id,
					'enrollment_year' => 2024,
					'status'          => 'active',
					'city_id'         => $almaty_city->id,
					'room_id'         => $room->id,
					'blood_type'      => 'O+',
					'parent_name'     => 'Parent ' . $studentData['name'],
					'parent_phone'    => '+77012345' . str_pad( $index, 3, '0', STR_PAD_LEFT ),
					'has_meal_plan'   => $index % 2 == 0,
				] )
			);

			// Assign bed to student
			if ( $bed ) {
				$bed->update( [ 'user_id' => $student->id ] );
			}

			// Create sample payments
			Payment::firstOrCreate( [ 
				'user_id'         => $student->id,
				'contract_number' => 'CONTRACT-2024-' . str_pad( $student->id, 4, '0', STR_PAD_LEFT ),
				'contract_date'   => now()->subDays( 30 ),
				'payment_date'    => now()->subDays( 20 ),
				'amount'          => 50000 + ( $index * 5000 ),
				'payment_method'  => 'Bank Transfer',
				'status'          => 'completed',
				'name'            => $student->name,
				'surname'         => '',
			] );
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
