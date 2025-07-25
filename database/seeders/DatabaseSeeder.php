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
			AdminSeeder::class,
			StudentSeeder::class,
			GuestSeeder::class,
			ConfigurationSeeder::class,
			DevelopmentSeeder::class,
			ProductionSeeder::class,
		] );
	}
}
