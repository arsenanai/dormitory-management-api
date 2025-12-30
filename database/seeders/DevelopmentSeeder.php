<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Models\AdminProfile;
use App\Models\Dormitory;
use App\Models\GuestProfile;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Seeder; // Import Carbon
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $adminRole = \App\Models\Role::firstOrCreate([ 'name' => 'admin' ]);
        $studentRole = \App\Models\Role::firstOrCreate([ 'name' => 'student' ]);
        $guestRole = \App\Models\Role::firstOrCreate(['name' => 'guest']);

        // Create Room Types
        $standardRoomType = RoomType::firstOrCreate([ 'name' => 'standard' ], [ 'capacity' => 2, 'daily_rate' => 10000.00, 'semester_rate' => 300000.00 ]);
        $luxRoomType = RoomType::firstOrCreate([ 'name' => 'lux' ], [ 'capacity' => 1, 'daily_rate' => 20000.00, 'semester_rate' => 500000.00 ]);

        // Create Blood Types
        $bloodTypes = [ 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-' ];
        foreach ($bloodTypes as $type) {
            \App\Models\BloodType::firstOrCreate([ 'name' => $type ]);
        }

        // Find or create the main admin user
        $adminUser = User::firstOrCreate(
            [ 'email' => config('app.admin_email', 'admin@email.com') ],
            [
                'name' => 'Main Admin',
                'password' => Hash::make(config('app.admin_password', 'supersecret')),
                'role_id' => $adminRole->id,
                'status' => 'active',
            ]
        );

        // Create and assign the main dormitory for the admin
        $adminDormitory = Dormitory::firstOrCreate(
            [ 'name' => 'Main Dormitory' ],
            [
                'address' => '123 Developer Lane',
                'gender' => 'male',
                'capacity' => 500,
                'admin_id' => $adminUser->id, // Assign admin directly to the dormitory
            ]
        );

        // Assign dormitory to admin via AdminProfile
        // This is for profile-specific info, while dormitory->admin_id is the main link.
        AdminProfile::updateOrCreate(
            [ 'user_id' => $adminUser->id ],
            [ 'dormitory_id' => $adminDormitory->id ]
        );

        // Create 50 rooms in the admin's dormitory using bulk insert
        $roomsData = [];
        $bedsData = [];
        for ($i = 1; $i <= 50; $i++) {
            $floor = (int)ceil($i / 10);
            $roomNum = $i % 10 === 0 ? 10 : $i % 10;
            // Ensure first floor has both standard and lux rooms for guests
            $roomType = ($floor === 1 && $i % 5 === 0) || ($floor > 1 && $i % 5 === 0) ? $luxRoomType : $standardRoomType;
            $isGuestRoom = ($floor === 1);
            $occupantType = $isGuestRoom ? 'guest' : 'student';

            $roomNumber = $floor . str_pad((string)$roomNum, 2, '0', STR_PAD_LEFT);
            $roomsData[] = [
                'number' => $roomNumber,
                'dormitory_id' => $adminDormitory->id,
                'room_type_id' => $roomType->id,
                'occupant_type' => $occupantType,
                'floor' => $floor,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert rooms
        Room::insert($roomsData);
        $rooms = Room::where('dormitory_id', $adminDormitory->id)->get();

        // Create beds for all rooms using bulk insert
        foreach ($rooms as $room) {
            $capacity = $room->roomType->capacity;
            for ($j = 1; $j <= $capacity; $j++) {
                $bedsData[] = [
                    'bed_number' => $j,
                    'room_id' => $room->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Bulk insert beds
        \App\Models\Bed::insert($bedsData);

        // Get all available beds to assign to students
        $availableBeds = \App\Models\Bed::whereHas('room', function ($q) use ($adminDormitory) {
            $q->where('dormitory_id', $adminDormitory->id)
              ->where('occupant_type', 'student');
        })->whereNull('user_id')->get();

        $bedIterator = 0;

        $faker = \Faker\Factory::create();

        // Create 500 students using bulk operations (optimized)
        $studentsData = [];
        $studentProfilesData = [];
        $bedAssignments = [];

        // Pre-generate all file paths to avoid individual file operations
        $allFilePaths = [];
        $base64Images = [
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/wcAAwAB/epv2AAAAABJRU5ErkJggg==',
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwAB/epv2AAAAABJRU5ErkJggg==',
        ];

        // Batch generate all files at once
        for ($i = 1; $i <= 500; $i++) {
            for ($j = 0; $j < 4; $j++) {
                $filename = 'student_' . $i . '_doc_' . ($j + 1) . '.png';
                $allFilePaths[$i][$j] = $this->storeBase64Image($base64Images[$j], 'student_files', $filename);
            }
        }

        for ($i = 1; $i <= 500; $i++) {
            $firstName = $faker->firstName;
            $lastName = $faker->lastName;
            $gender = 'male';
            $email = $faker->unique()->safeEmail;

            // Assign a bed if available
            $bed = $availableBeds[$bedIterator] ?? null;
            if ($bed) {
                $bedAssignments[$bed->id] = $i; // Map bed_id to student index
                $bedIterator++;
            }

            $studentsData[] = [
                'name' => "$firstName $lastName",
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone_numbers' => json_encode([$faker->phoneNumber]),
                'password' => Hash::make('password'),
                'role_id' => $studentRole->id,
                'status' => 'active',
                'dormitory_id' => $bed ? optional($bed->room)->dormitory_id : null,
                'room_id' => $bed ? $bed->room_id : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $studentProfilesData[] = [
                'iin' => $faker->unique()->numerify('############'),
                'student_id' => 'STU' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
                'faculty' => $faker->randomElement(['Engineering', 'Business', 'Medicine', 'Law', 'Arts']),
                'specialist' => $faker->randomElement(['Computer Science', 'Marketing', 'General Medicine']),
                'enrollment_year' => $faker->numberBetween(2020, 2024),
                'gender' => $gender,
                'blood_type' => $faker->randomElement($bloodTypes),
                'country' => $faker->country,
                'region' => $faker->city,
                'city' => $faker->city,
                'emergency_contact_name' => $faker->name,
                'emergency_contact_phone' => $faker->phoneNumber,
                'emergency_contact_type' => $faker->randomElement(['parent', 'guardian', 'other']),
                'emergency_contact_email' => $faker->safeEmail,
                'deal_number' => 'DEAL-' . $faker->numerify('######'),
                'agree_to_dormitory_rules' => true,
                'has_meal_plan' => $faker->boolean,
                'date_of_birth' => $faker->dateTimeBetween('-25 years', '-18 years'),
                'allergies' => $faker->optional(0.2)->sentence,
                'violations' => $faker->optional(0.1)->sentence,
                'files' => json_encode($allFilePaths[$i] ?? []),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert students
        User::insert($studentsData);

        // Get the inserted students with their IDs (more efficient query)
        $lastStudentId = User::where('role_id', $studentRole->id)->max('id');
        $firstStudentId = $lastStudentId - 499;
        $insertedStudents = User::where('role_id', $studentRole->id)
            ->whereBetween('id', [$firstStudentId, $lastStudentId])
            ->orderBy('id')
            ->get();

        // Prepare student profiles with user_ids
        foreach ($insertedStudents as $index => $student) {
            $studentProfilesData[$index]['user_id'] = $student->id;
        }

        // Bulk insert student profiles
        \App\Models\StudentProfile::insert($studentProfilesData);

        // Bulk update beds with user assignments
        $bedUpdateCases = [];
        $bedIds = [];
        foreach ($bedAssignments as $bedId => $studentIndex) {
            $studentId = $insertedStudents[$studentIndex - 1]->id;
            $bedUpdateCases[] = "WHEN {$bedId} THEN {$studentId}";
            $bedIds[] = $bedId;
        }

        if (!empty($bedIds)) {
            \App\Models\Bed::whereIn('id', $bedIds)->update([
                'user_id' => \DB::raw("CASE id " . implode(' ', $bedUpdateCases) . " END"),
                'is_occupied' => true,
                'updated_at' => now(),
            ]);
        }

        // Create 5 Guests, leaving some rooms available
        $guestRooms = Room::where('dormitory_id', $adminDormitory->id)
            ->where('occupant_type', 'guest')
            ->with('beds')
            ->inRandomOrder()->take(5)->get();
        foreach ($guestRooms as $guestRoom) {
            $availableBed = $guestRoom->beds()->where('is_occupied', false)->first();
            /** @var \App\Models\Bed $availableBed */

            if ($availableBed) {
                $firstName = $faker->firstName;
                $lastName = $faker->lastName;
                $guestUser = User::create([
                    'name'          => $firstName . ' ' . $lastName,
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                    'email'         => $faker->unique()->safeEmail,
                    'phone_numbers' => json_encode([ $faker->phoneNumber ]),
                    'password'      => Hash::make('password'),
                    'role_id'       => $guestRole->id,
                    'status'        => 'active',
                    'dormitory_id'  => $adminDormitory->id,
                    'room_id'       => $guestRoom->id,
                ]);

                \App\Models\GuestProfile::create([
                    'user_id'          => $guestUser->id,
                    'purpose_of_visit' => $faker->sentence,
                    'visit_start_date' => now()->subDays(rand(1, 5)),
                    'visit_end_date'   => now()->addDays(rand(2, 10)),
                    'is_approved'      => true,
                    'bed_id'           => $availableBed->id,
                ]);

                $availableBed->update(['is_occupied' => true, 'user_id' => $guestUser->id]);
            }
        }

        // Create 10 Messages from Admin using bulk insert
        $messagesData = [];
        for ($i = 0; $i < 10; $i++) {
            $messagesData[] = [
                'sender_id' => $adminUser->id,
                'receiver_id' => null,
                'recipient_type' => 'dormitory',
                'dormitory_id' => $adminDormitory->id,
                'title' => $faker->sentence(3),
                'content' => $faker->realText(200),
                'type' => 'announcement',
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Message::insert($messagesData);

        // Define base64 images for payment checks
        $base64PaymentCheckImages = [
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/wcAAwAB/epv2AAAAABJRU5ErkJggg==', // Small transparent PNG
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', // Another small transparent PNG
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', // Another small transparent PNG
        ];
        $paymentImageIndex = 0;

        // Create all payments using a single bulk insert (optimized)
        $studentsForPayment = User::where('role_id', $studentRole->id)->get();
        $guestsForPayment = GuestProfile::all();
        $paymentsData = [];
        $paymentImageIndex = 0;

        // First semester payments for students
        foreach ($studentsForPayment as $student) {
            $filename = 'payment_check_student_' . $student->id . '_' . uniqid() . '.png';
            $paymentCheckPath = $this->storeBase64Image($base64PaymentCheckImages[$paymentImageIndex % 3], 'payment_checks', $filename);
            $paymentImageIndex++;

            $paymentsData[] = [
                'user_id' => $student->id,
                'amount' => $faker->randomFloat(2, 50000, 150000),
                'deal_number' => 'DEAL-' . $faker->unique()->numerify('######'),
                'deal_date' => now()->subDays(rand(1, 30)),
                'date_from' => Carbon::parse('2025-01-01'),
                'date_to' => Carbon::parse('2025-06-01'),
                'payment_check' => $paymentCheckPath,
                'status' => PaymentStatus::Completed,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Second semester payments for students
        foreach ($studentsForPayment as $student) {
            $filename = 'payment_check_student_' . $student->id . '_' . uniqid() . '.png';
            $paymentCheckPath = $this->storeBase64Image($base64PaymentCheckImages[$paymentImageIndex % 3], 'payment_checks', $filename);
            $paymentImageIndex++;

            $paymentsData[] = [
                'user_id' => $student->id,
                'amount' => $faker->randomFloat(2, 50000, 150000),
                'deal_number' => 'DEAL-' . $faker->unique()->numerify('######'),
                'deal_date' => now()->subDays(rand(1, 30)),
                'date_from' => Carbon::parse('2025-09-01'),
                'date_to' => Carbon::parse('2026-01-01'),
                'payment_check' => $paymentCheckPath,
                'status' => PaymentStatus::Completed,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Guest payments
        foreach ($guestsForPayment as $guest) {
            $filename = 'payment_check_guest_' . $guest->user_id . '_' . uniqid() . '.png';
            $paymentCheckPath = $this->storeBase64Image($base64PaymentCheckImages[$paymentImageIndex % 3], 'payment_checks', $filename);
            $paymentImageIndex++;

            $paymentsData[] = [
                'user_id' => $guest->user_id,
                'amount' => $faker->randomFloat(2, 10000, 30000),
                'deal_date' => now()->subDays(rand(1, 30)),
                'deal_number' => 'DEAL-' . $faker->unique()->numerify('######'),
                'date_from' => $guest->visit_start_date,
                'date_to' => $guest->visit_end_date,
                'payment_check' => $paymentCheckPath,
                'status' => PaymentStatus::Completed,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Single bulk insert for all payments
        Payment::insert($paymentsData);

        $this->command->info('Development data seeded successfully!');
    }

    /**
     * Decodes a base64 string and stores it as a file in the local disk.
     *
     * @param string $base64String The base64 encoded image data.
     * @param string $directory The directory within storage/app/local to save the file.
     * @param string $filename The desired filename.
     * @return string The path to the stored file.
     */
    private function storeBase64Image(string $base64String, string $directory, string $filename): string
    {
        $imageData = base64_decode($base64String);
        $path = $directory . '/' . $filename;
        Storage::disk('local')->put($path, $imageData);
        return $path;
    }
}
