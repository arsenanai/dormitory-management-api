<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder {
	/**
	 * Run the database seeders for production.
	 */
	public function run(): void {
		// Create roles
		$sudoRole = Role::firstOrCreate( [ 'name' => 'sudo' ] );
		$adminRole = Role::firstOrCreate( [ 'name' => 'admin' ] );
		$userRole = Role::firstOrCreate( [ 'name' => 'user' ] );
	}
}
