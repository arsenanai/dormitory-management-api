<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\Dormitory;

class AdminSeeder extends Seeder {
	public function run(): void {
		// Create a default sudo user
		$sudoRole = Role::where( 'name', 'sudo' )->first();
		User::firstOrCreate( [ 
			'email' => env( 'SUDO_EMAIL', 'sudo@email.com' ),
		], [ 
			'name'              => env( 'SUDO_NAME', 'System Administrator' ),
			'email'             => env( 'SUDO_EMAIL', 'sudo@email.com' ),
			'password'          => Hash::make( env( 'SUDO_PASSWORD', 'supersecret' ) ),
			'role_id'           => $sudoRole ? $sudoRole->id : 1,
			'status'            => 'active',
			'email_verified_at' => now(),
		] );

		// Create a default admin user
		$adminRole = Role::where( 'name', 'admin' )->first();

		$adminUser = User::firstOrCreate( [ 
			'email' => env( 'ADMIN_EMAIL', 'admin@email.com' ),
		], [ 
			'name'              => env( 'ADMIN_NAME', 'Admin' ),
			'email'             => env( 'ADMIN_EMAIL', 'admin@email.com' ),
			'password'          => Hash::make( env( 'ADMIN_PASSWORD', 'supersecret' ) ),
			'role_id'           => $adminRole ? $adminRole->id : 2,
			'status'            => 'active',
			'email_verified_at' => now(),
		] );

		// Create AdminProfile for the admin user
		\App\Models\AdminProfile::firstOrCreate( [ 
			'user_id' => $adminUser->id,
		], [ 
			'position'        => 'Dormitory Administrator',
			'department'      => 'Student Housing',
			'office_phone'    => '+7 777 123 45 67',
			'office_location' => 'Main Office',
		] );
	}
}