<?php

namespace Database\Seeders;

use Database\Factories\GuestFactory;
use Illuminate\Database\Seeder;

class GuestSeeder extends Seeder
{
    public function run(): void
    {
        GuestFactory::new()->count(3)->create();
    }
}
