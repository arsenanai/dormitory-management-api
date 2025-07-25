<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Database\Factories\AdminFactory;

class AdminSeeder extends Seeder {
	public function run(): void {
		AdminFactory::new()->count( 2 )->create();
	}
}