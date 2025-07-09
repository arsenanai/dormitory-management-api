<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder {
	/**
	 * Run the database seeds.
	 */
	public function run(): void {
		$role = Role::firstOrCreate( [ 
			'name' => 'sudo'
		] );
		User::create( [ 
			'name'          => env( 'ADMIN_NAME', 'Admin' ),
			'first_name'    => env( 'ADMIN_FIRST_NAME', 'Admin' ),
			'last_name'     => env( 'ADMIN_LAST_NAME', 'User' ),
			'email'         => env( 'ADMIN_EMAIL', 'admin@email.com' ),
			'phone_numbers' => json_encode( [ env( 'ADMIN_PHONE', '+77001234567' ) ] ),
			'room_id'       => null,
			'password'      => bcrypt( env( 'ADMIN_PASSWORD', 'supersecret' ) ),
			'status'        => 'active',
			'role_id'       => $role->id,
		] );
	}
}
