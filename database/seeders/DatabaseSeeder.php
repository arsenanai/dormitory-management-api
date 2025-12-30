<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Common seeders that run in all environments
        $this->call([
            RoleSeeder::class,
            AdminSeeder::class, // Only this seeder creates admin/sudo users
            RoomTypeSeeder::class, // Add room types (standard and lux)
            ConfigurationSeeder::class,
            BloodTypeSeeder::class // Add blood types
        ]);

        // Environment-specific seeders
        if (App::isLocal()) {
            $this->call([
                DevelopmentSeeder::class,
            ]);
        }
    }
}
