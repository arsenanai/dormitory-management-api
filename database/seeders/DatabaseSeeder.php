<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
	/**
	 * Seed the application's database.
	 */
	public function run(): void {
		$this->call( [ 
			RoleSeeder::class,
			AdminSeeder::class, // Only this seeder creates admin/sudo users
			RoomTypeSeeder::class, // Add room types (standard and lux)
			StudentSeeder::class,
			GuestSeeder::class,
			ConfigurationSeeder::class,
			BloodTypeSeeder::class, // Add blood types
			KazakhstanSeeder::class, // Add Kazakhstan regions and cities
			DevelopmentSeeder::class, // Only domain-specific data, no admin/sudo users
			ProductionSeeder::class,  // Only domain-specific data, no admin/sudo users
		] );
	}
}
