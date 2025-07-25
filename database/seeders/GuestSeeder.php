<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Database\Factories\GuestFactory;

class GuestSeeder extends Seeder {
	public function run(): void {
		GuestFactory::new()->count( 3 )->create();
	}
}