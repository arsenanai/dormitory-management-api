<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create a default sudo user
        $sudoRole = Role::where('name', 'sudo')->first();
        User::firstOrCreate([
            'email' => config('app.sudo_email', 'sudo@email.com'),
        ], [
            'name'              => config('app.sudo_name', 'System Administrator'),
            'email'             => config('app.sudo_email', 'sudo@email.com'),
            'password'          => Hash::make(config('app.sudo_password', 'supersecret') . ''),
            'role_id'           => $sudoRole ? $sudoRole->id : 1,
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        // Create a default admin user
        $adminRole = Role::where('name', 'admin')->first();

        $adminUser = User::firstOrCreate([
            'email' => config('app.admin_email', 'admin@email.com'),
        ], [
            'name'              => config('app.admin_name', 'Admin'),
            'email'             => config('app.admin_email', 'admin@email.com'),
            'password'          => Hash::make(config('app.admin_password', 'supersecret') . ''),
            'role_id'           => $adminRole ? $adminRole->id : 2,
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        // Create AdminProfile for the admin user
        \App\Models\AdminProfile::firstOrCreate([
            'user_id' => $adminUser->id,
        ], [
            'position'        => 'Dormitory Administrator',
            'department'      => 'Student Housing',
            'office_phone'    => '+7 777 123 45 67',
            'office_location' => 'Main Office',
        ]);
    }
}
