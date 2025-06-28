<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

		$role = Role::firstOrCreate(
			[ 'name' => 'sudo' ]
		);
		User::create( [ 
			'iin'                      => env( 'ADMIN_IIN', '000000000' ),
			'name'                     => env( 'ADMIN_NAME', 'Admin' ),
			'faculty'                  => env( 'ADMIN_FACULTY', 'Administration' ),
			'specialist'               => env( 'ADMIN_SPECIALIST', 'System Admin' ),
			'enrollment_year'          => env( 'ADMIN_ENROLLMENT_YEAR', '2020' ),
			'gender'                   => env( 'ADMIN_GENDER', 'male' ),
			'email'                    => env( 'ADMIN_EMAIL', 'admin@sdu.edu.kz' ),
			'phone_numbers'            => json_encode( [ env( 'ADMIN_PHONE', '+77001234567' ) ] ),
			'room_id'                  => null,
			'password'                 => bcrypt( env( 'ADMIN_PASSWORD', 'supersecret' ) ),
			'deal_number'              => env( 'ADMIN_DEAL_NUMBER', 'ADMIN001' ),
			'city_id'                  => null,
			'files'                    => json_encode( [ null, null, null, null ] ),
			'agree_to_dormitory_rules' => true,
			'status'                   => 'active',
			'role_id'                  => $role->id,
		] );
    }
}
