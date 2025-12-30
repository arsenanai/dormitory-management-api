<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::firstOrCreate([ 'name' => 'sudo' ]);
        $studentRole = Role::firstOrCreate([ 'name' => 'student' ]);
        User::create([
            'name'          => config('app.admin_name', 'Admin'),
            'first_name'    => config('app.admin_first_name', 'Admin'),
            'last_name'     => config('app.admin_last_name', 'User'),
            'email'         => config('app.admin_email', 'admin_' . uniqid() . '@email.com'),
            'phone_numbers' => json_encode([ config('app.admin_phone', '+77001234567') ]),
            'room_id'       => null,
            'password'      => bcrypt(config('app.admin_password', 'supersecret')),
            'status'        => 'active',
            'role_id'       => $role->id,
        ]);
        User::create([
            'name'          => 'Alice Student',
            'first_name'    => 'Alice',
            'last_name'     => 'Student',
            'email'         => 'alice_' . uniqid() . '@student.local',
            'phone_numbers' => json_encode([ '+77001234568' ]),
            'room_id'       => null,
            'password'      => bcrypt('password'),
            'status'        => 'active',
            'role_id'       => $studentRole->id,
        ]);
    }
}
