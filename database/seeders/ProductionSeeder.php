<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder {
	/**
	 * Run the database seeders for production.
	 */
	public function run(): void {
		// Create roles
		$sudoRole = Role::firstOrCreate( [ 'name' => 'sudo' ] );
		$adminRole = Role::firstOrCreate( [ 'name' => 'admin' ] );
		$userRole = Role::firstOrCreate( [ 'name' => 'user' ] );

		// Create default sudo user
		User::firstOrCreate(
			[ 'email' => 'admin@dormitory.com' ],
			[ 
				'name'              => 'System Administrator',
				'email'             => 'admin@dormitory.com',
				'password'          => Hash::make( 'change-this-password' ),
				'role_id'           => $sudoRole->id,
				'email_verified_at' => now(),
			]
		);

		// Create sample admin user
		User::firstOrCreate(
			[ 'email' => 'dorm-admin@dormitory.com' ],
			[ 
				'name'              => 'Dormitory Admin',
				'email'             => 'dorm-admin@dormitory.com',
				'password'          => Hash::make( 'change-this-password' ),
				'role_id'           => $adminRole->id,
				'email_verified_at' => now(),
			]
		);
	}
}
