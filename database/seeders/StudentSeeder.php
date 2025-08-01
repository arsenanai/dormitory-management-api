<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Database\Factories\StudentFactory;

class StudentSeeder extends Seeder {
	public function run(): void {
		StudentFactory::new()->count( 5 )->create();
	}
}