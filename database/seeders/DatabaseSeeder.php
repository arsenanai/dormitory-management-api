<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
	/**
	 * Seed the application's database.
	 */
	public function run(): void {
		$this->call( RoleSeeder::class);
		$this->call( UserSeeder::class);

		// Only run development seeder in non-production environments
		if ( ! app()->environment( 'production' ) ) {
			$this->call( DevelopmentSeeder::class);
		}
	}
}
