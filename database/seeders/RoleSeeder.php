<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([ 'sudo', 'admin', 'student', 'visitor', 'guest' ] as $role) {
            Role::firstOrCreate([ 'name' => $role ]);
        }
    }
}
