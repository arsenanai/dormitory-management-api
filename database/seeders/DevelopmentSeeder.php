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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DevelopmentSeeder extends Seeder
{
    /**
     * Generate unique email using deterministic algorithm
     */
    private function generateUniqueEmail(int $index, string $domain = 'example.com'): string
    {
        $names = [ 'john', 'jane', 'mike', 'sarah', 'alex', 'emma', 'david', 'lisa', 'james', 'mary' ];
        $nameIndex = $index % count($names);
        $suffix = floor($index / count($names)) + 1;
        return "{$names[ $nameIndex ]}{$suffix}@{$domain}";
    }

    /**
     * Generate unique IIN using deterministic algorithm
     */
    private function generateUniqueIIN(int $index): string
    {
        $base = str_pad((string) $index, 8, '0', STR_PAD_LEFT);
        $checksum = ($index % 10);
        return $base . $checksum . str_pad((string) ($index % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Generate unique deal number using deterministic algorithm
     */
    private function generateUniqueDealNumber(int $index): string
    {
        return 'DEAL-' . str_pad((string) (100000 + $index), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate deterministic phone number
     */
    private function generatePhoneNumber(int $index): string
    {
        $prefixes = [ '+1', '+44', '+91', '+86', '+81', '+49', '+33', '+61' ];
        $prefix = $prefixes[ $index % count($prefixes) ];
        $number = str_pad((string) (1000000 + ($index * 12345) % 9000000), 7, '0', STR_PAD_LEFT);
        return $prefix . ' ' . substr($number, 0, 3) . ' ' . substr($number, 3, 4);
    }

    /**
     * Get deterministic name from pre-generated arrays
     */
    private function getFirstName(int $index): string
    {
        $firstNames = [
            'James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Charles',
            'Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan', 'Jessica', 'Sarah', 'Karen',
            'Lisa', 'Nancy', 'Betty', 'Helen', 'Sandra', 'Donna', 'Carol', 'Ruth', 'Sharon', 'Michelle'
        ];
        return $firstNames[ $index % count($firstNames) ];
    }

    private function getLastName(int $index): string
    {
        $lastNames = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
            'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
            'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson'
        ];
        return $lastNames[ ($index + 7) % count($lastNames) ];
    }

    /**
     * Get deterministic random element from array
     */
    private function getRandomElement(array $array, int $index): string
    {
        return $array[ $index % count($array) ];
    }

    /**
     * Get deterministic random number
     */
    private function getRandomNumber(int $min, int $max, int $index): int
    {
        return $min + ($index % ($max - $min + 1));
    }

    /**
     * Get deterministic boolean
     */
    private function getRandomBoolean(int $index): bool
    {
        return ($index % 2) === 0;
    }

    /**
     * Get deterministic random float
     */
    private function getRandomFloat(float $min, float $max, int $index): float
    {
        $range = $max - $min;
        return round($min + ($range * (($index * 7) % 100) / 100), 2);
    }

    /**
     * Get deterministic date between range
     */
    private function getRandomDate(string $startDate, string $endDate, int $index): string
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $interval = $start->diff($end);
        $days = $interval->days;
        $randomDays = $index % ($days + 1);
        $date = clone $start;
        $date->add(new \DateInterval("P{$randomDays}D"));
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Batch store base64 images for better performance
     */
    private function batchStoreImages(array $base64Images, string $directory, array $filenames): array
    {
        $paths = [];
        foreach ($filenames as $i => $filename) {
            $imageData = base64_decode($base64Images[ $i % count($base64Images) ]);
            $path = $directory . '/' . $filename;
            Storage::disk('public')->put($path, $imageData);
            $paths[] = $path;
        }
        return $paths;
    }

    /**
     * Generate real avatar images using GD library
     */
    private function generateAvatarImages(): array
    {
        $avatars = [];
        $colors = [
            [52, 152, 219],   // Blue
            [46, 204, 113],   // Green
            [231, 76, 60],    // Red
            [155, 89, 182],   // Purple
            [241, 196, 15],   // Yellow/Orange
        ];

        foreach ($colors as $color) {
            // Create a 100x100 image
            $image = imagecreatetruecolor(100, 100);
            $bgColor = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            imagefill($image, 0, 0, $bgColor);

            // Add a simple circle or pattern for visual interest
            $white = imagecolorallocate($image, 255, 255, 255);
            $centerX = 50;
            $centerY = 50;
            $radius = 35;
            imagefilledellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $white);

            // Add a smaller circle inside
            $innerColor = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            imagefilledellipse($image, $centerX, $centerY, $radius, $radius, $innerColor);

            // Capture output
            ob_start();
            imagepng($image);
            $imageData = ob_get_contents();
            ob_end_clean();
            imagedestroy($image);

            $avatars[] = base64_encode($imageData);
        }

        return $avatars;
    }

    /**
     * Generate room type photos using GD library
     *
     * @param string $roomTypeName Name of the room type (e.g., 'standard', 'lux')
     * @param int $count Number of photos to generate
     * @return array Array of file paths
     */
    private function generateRoomTypePhotos(string $roomTypeName, int $count): array
    {
        $photos = [];
        $directory = 'room-type';

        // Different color schemes for different room types
        $colorSchemes = [
            'standard' => [
                [200, 200, 200], // Light gray
                [180, 200, 220], // Light blue-gray
                [220, 220, 200], // Light beige
            ],
            'lux' => [
                [255, 215, 0],   // Gold
                [192, 192, 192],  // Silver
                [255, 192, 203],  // Pink
            ],
        ];

        $colors = $colorSchemes[$roomTypeName] ?? $colorSchemes['standard'];

        for ($i = 0; $i < $count; $i++) {
            $color = $colors[$i % count($colors)];

            // Create a 800x600 image (room photo size)
            $image = imagecreatetruecolor(800, 600);
            $bgColor = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            imagefill($image, 0, 0, $bgColor);

            // Add some visual elements to make it look like a room
            $darkerColor = imagecolorallocate(
                $image,
                max(0, $color[0] - 30),
                max(0, $color[1] - 30),
                max(0, $color[2] - 30)
            );

            // Draw a "window" rectangle
            $windowX = 150;
            $windowY = 100;
            $windowW = 200;
            $windowH = 250;
            imagefilledrectangle($image, $windowX, $windowY, $windowX + $windowW, $windowY + $windowH, $darkerColor);

            // Draw a "bed" rectangle
            $bedX = 400;
            $bedY = 400;
            $bedW = 300;
            $bedH = 150;
            imagefilledrectangle($image, $bedX, $bedY, $bedX + $bedW, $bedY + $bedH, $darkerColor);

            // Add text label
            $textColor = imagecolorallocate($image, 100, 100, 100);
            imagestring($image, 5, 50, 50, ucfirst($roomTypeName) . ' Room Photo ' . ($i + 1), $textColor);

            // Generate filename
            $filename = $roomTypeName . '_photo_' . ($i + 1) . '.png';
            $path = $directory . '/' . $filename;

            // Save image to storage
            ob_start();
            imagepng($image);
            $imageData = ob_get_contents();
            ob_end_clean();
            imagedestroy($image);

            Storage::disk('public')->put($path, $imageData);
            $photos[] = $path;
        }

        return $photos;
    }

    public function run(): void
    {
        $startTime = microtime(true);
        $this->command->info('Starting DevelopmentSeeder...');

        // Create roles
        $stepStart = microtime(true);
        $adminRole = \App\Models\Role::firstOrCreate([ 'name' => 'admin' ]);
        $studentRole = \App\Models\Role::firstOrCreate([ 'name' => 'student' ]);
        $guestRole = \App\Models\Role::firstOrCreate([ 'name' => 'guest' ]);
        $this->command->info('Roles created: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create Room Types
        $stepStart = microtime(true);
        $standardRoomType = RoomType::firstOrCreate([ 'name' => 'standard' ], [ 'capacity' => 2, 'daily_rate' => 10000.00, 'semester_rate' => 300000.00 ]);
        $luxRoomType = RoomType::firstOrCreate([ 'name' => 'lux' ], [ 'capacity' => 1, 'daily_rate' => 20000.00, 'semester_rate' => 500000.00 ]);

        // Generate and store photos for room types
        $standardPhotos = $this->generateRoomTypePhotos('standard', 3);
        $luxPhotos = $this->generateRoomTypePhotos('lux', 3);

        $standardRoomType->update([ 'photos' => $standardPhotos ]);
        $luxRoomType->update([ 'photos' => $luxPhotos ]);

        $this->command->info('Room types created: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create Blood Types
        $stepStart = microtime(true);
        $bloodTypes = [ 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-' ];
        foreach ($bloodTypes as $type) {
            \App\Models\BloodType::firstOrCreate([ 'name' => $type ]);
        }
        $this->command->info('Blood types created: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Find or create the main admin user
        $stepStart = microtime(true);
        $adminUser = User::firstOrCreate(
            [ 'email' => config('app.admin_email', 'admin@email.com') ],
            [
                'name'     => 'Main Admin',
                'password' => Hash::make(config('app.admin_password', 'supersecret') . ''),
                'role_id'  => $adminRole->id,
                'status'   => 'active',
            ]
        );
        $this->command->info('Admin user created: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create and assign the main dormitory for the admin
        $stepStart = microtime(true);
        $adminDormitory = Dormitory::firstOrCreate(
            [ 'name' => 'Main Dormitory' ],
            [
                'address'  => '123 Developer Lane',
                'gender'   => 'male',
                'capacity' => 500,
                'admin_id' => $adminUser->id,
            ]
        );
        $this->command->info('Admin dormitory created: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Assign dormitory to admin via AdminProfile
        $stepStart = microtime(true);
        AdminProfile::updateOrCreate(
            [ 'user_id' => $adminUser->id ],
            [ 'dormitory_id' => $adminDormitory->id ]
        );
        $this->command->info('Admin profile created: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create 50 rooms in the admin's dormitory using bulk insert
        $stepStart = microtime(true);
        $roomsData = [];
        $bedsData = [];
        for ($i = 1; $i <= 50; $i++) {
            $floor = (int) ceil($i / 10);
            $roomNum = $i % 10 === 0 ? 10 : $i % 10;
            // Ensure first floor has both standard and lux rooms for guests
            $roomType = ($floor === 1 && $i % 5 === 0) || ($floor > 1 && $i % 5 === 0) ? $luxRoomType : $standardRoomType;
            $isGuestRoom = ($floor === 1);
            $occupantType = $isGuestRoom ? 'guest' : 'student';

            $roomNumber = $floor . str_pad((string) $roomNum, 2, '0', STR_PAD_LEFT);
            $roomsData[] = [
                'number'        => $roomNumber,
                'dormitory_id'  => $adminDormitory->id,
                'room_type_id'  => $roomType->id,
                'occupant_type' => $occupantType,
                'floor'         => $floor,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }
        $this->command->info('Room data prepared: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Bulk insert rooms and get inserted IDs in one query
        $stepStart = microtime(true);
        Room::insert($roomsData);
        $rooms = Room::where('dormitory_id', $adminDormitory->id)
            ->with('roomType:id,capacity')
            ->orderBy('id')
            ->get([ 'id', 'room_type_id' ]);
        $roomMap = $rooms->keyBy('id');
        $this->command->info('Rooms inserted and fetched: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create beds for all rooms using bulk insert with pre-calculated data
        $stepStart = microtime(true);
        $bedsData = [];
        foreach ($rooms as $room) {
            $roomWithRoomType = $roomMap[ $room->id ] ?? null;
            if (! $roomWithRoomType) {
                continue;
            }
            $roomType = $roomWithRoomType->roomType;
            /** @var int $capacity */
            $capacity = $roomType?->capacity ?? 0;
            for ($j = 1; $j <= $capacity; $j++) {
                $bedsData[] = [
                    'bed_number' => $j,
                    'room_id'    => $room->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        \App\Models\Bed::insert($bedsData);
        $availableBeds = \App\Models\Bed::whereHas('room', function ($q) use ($adminDormitory) {
            $q->where('dormitory_id', $adminDormitory->id)
                ->where('occupant_type', 'student');
        })->whereNull('user_id')->pluck('id')->toArray();
        $this->command->info('Beds created and available beds fetched: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        $faker = \Faker\Factory::create();

        // Create 500 students using optimized bulk operations
        $studentsData = [];
        $studentProfilesData = [];
        $bedAssignments = []; // Will store: bedId => email

        // Pre-generate all filenames for batch processing
        $stepStart = microtime(true);
        $studentFilenames = [];
        for ($i = 1; $i <= 500; $i++) {
            // 2 document files + 1 avatar file (index 2)
            for ($j = 0; $j < 2; $j++) {
                $studentFilenames[] = 'student_' . $i . '_doc_' . ($j + 1) . '.png';
            }
            $studentFilenames[] = 'avatar_' . $i . '.png';
        }
        $this->command->info('Student filenames generated: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Batch store all student files at once (2 documents + 1 avatar)
        $stepStart = microtime(true);
        $base64Images = [
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/wcAAwAB/epv2AAAAABJRU5ErkJggg==',
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        ];
        $avatarImages = $this->generateAvatarImages();

        // Combine document images (2) and avatar images (1)
        $allImages = array_merge($base64Images, $avatarImages);
        $allFilePaths = $this->batchStoreImages($allImages, 'student_files', $studentFilenames);

        // Reorganize file paths by student index (2 docs + 1 avatar per student)
        $studentFiles = [];
        $fileIndex = 0;
        for ($i = 1; $i <= 500; $i++) {
            // Create array with 4 elements: [doc1, doc2, avatar, null]
            $studentFiles[ $i ] = [
                $allFilePaths[ $fileIndex ] ?? null,     // Document 1
                $allFilePaths[ $fileIndex + 1 ] ?? null, // Document 2
                $allFilePaths[ $fileIndex + 2 ] ?? null, // Index 2: Avatar
                null                                     // Index 3: empty
            ];
            $fileIndex += 3;
        }
        $this->command->info('Student files and avatars stored and organized: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Generate student data
        $stepStart = microtime(true);

        // Pre-define arrays for deterministic selection
        $faculties = [ 'Engineering', 'Business', 'Medicine', 'Law', 'Arts' ];
        $specialists = [ 'Computer Science', 'Marketing', 'General Medicine' ];
        $bloodTypesArray = [ 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-' ];
        $countries = [ 'USA', 'Canada', 'UK', 'Germany', 'France', 'Japan', 'China', 'India', 'Brazil', 'Australia' ];
        $cities = [ 'New York', 'London', 'Tokyo', 'Paris', 'Berlin', 'Sydney', 'Toronto', 'Mumbai', 'São Paulo', 'Beijing' ];
        $emergencyTypes = [ 'parent', 'guardian', 'other' ];

        // Pre-generate the hashed password once to avoid expensive hashing in loop
        $hashedPassword = Hash::make('password');

        // Pre-fetch bed room mappings to avoid individual queries
        $bedRoomMappings = [];
        if (! empty($availableBeds)) {
            $bedsWithRooms = \App\Models\Bed::whereIn('id', $availableBeds)
                ->with('room')
                ->get([ 'id', 'room_id' ]);
            foreach ($bedsWithRooms as $bed) {
                $bedRoomMappings[ $bed->id ] = $bed->room_id;
            }
        }

        for ($i = 1; $i <= 500; $i++) {
            $firstName = $this->getFirstName($i);
            $lastName = $this->getLastName($i);
            $gender = 'male';
            $email = $this->generateUniqueEmail($i);

            // Assign a bed if available
            $bedId = $availableBeds[ $i - 1 ] ?? null;
            $roomId = $bedId ? ($bedRoomMappings[ $bedId ] ?? null) : null;
            if ($bedId) {
                $bedAssignments[ $bedId ] = $email; // Store email instead of index for reliable mapping
            }

            $studentsData[] = [
                'name'              => "$firstName $lastName",
                'first_name'        => $firstName,
                'last_name'         => $lastName,
                'email'             => $email,
                'phone_numbers'     => json_encode([ $this->generatePhoneNumber($i) ]),
                'password'          => $hashedPassword,
                'role_id'           => $studentRole->id,
                'status'            => 'active',
                'email_verified_at' => now(),
                'remember_token'    => Str::random(10),
                'room_id'           => $roomId,
                'dormitory_id'      => $roomId ? ($adminDormitory->id) : null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            $studentProfilesData[] = [
                'iin'                      => $this->generateUniqueIIN($i),
                'student_id'               => 'STU' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'faculty'                  => $this->getRandomElement($faculties, $i),
                'specialist'               => $this->getRandomElement($specialists, $i + 3),
                'enrollment_year'          => $this->getRandomNumber(2020, 2024, $i),
                'gender'                   => $gender,
                'blood_type'               => $this->getRandomElement($bloodTypesArray, $i + 7),
                'country'                  => $this->getRandomElement($countries, $i + 11),
                'region'                   => $this->getRandomElement($cities, $i + 13),
                'city'                     => $this->getRandomElement($cities, $i + 17),
                'emergency_contact_name'   => $this->getFirstName($i + 19) . ' ' . $this->getLastName($i + 19),
                'emergency_contact_phone'  => $this->generatePhoneNumber($i + 23),
                'emergency_contact_type'   => $this->getRandomElement($emergencyTypes, $i),
                'emergency_contact_email'  => $this->generateUniqueEmail(2000 + $i, 'emergency.com'),
                'deal_number'              => $this->generateUniqueDealNumber($i),
                'agree_to_dormitory_rules' => true,
                'date_of_birth'            => $this->getRandomDate('-25 years', '-18 years', $i),
                'allergies'                => ($i % 5 === 0) ? 'Mild pollen allergies' : null,
                'violations'               => ($i % 10 === 0) ? 'Late arrival once' : null,
                'files'                    => json_encode($studentFiles[ $i ] ?? []),
                'created_at'               => now(),
                'updated_at'               => now(),
            ];
        }
        $this->command->info('Student data generated: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Bulk insert students and get IDs efficiently
        $stepStart = microtime(true);
        User::insert($studentsData);
        // Fetch students by email to create email-to-ID mapping
        $emails = array_column($studentsData, 'email');
        $insertedStudents = User::where('role_id', $studentRole->id)
            ->whereIn('email', $emails)
            ->get([ 'id', 'email' ]);
        $emailToStudentMap = [];
        foreach ($insertedStudents as $student) {
            $emailToStudentMap[ $student->email ] = $student->id;
        }
        $this->command->info('Students inserted and IDs fetched: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Prepare student profiles with user_ids
        $stepStart = microtime(true);
        foreach ($studentsData as $index => $studentData) {
            $studentProfilesData[ $index ]['user_id'] = $emailToStudentMap[ $studentData['email'] ] ?? null;
        }
        \App\Models\StudentProfile::insert($studentProfilesData);
        $this->command->info('Student profiles inserted: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Bulk update beds with user assignments using single query
        $stepStart = microtime(true);
        if (! empty($bedAssignments)) {
            $bedUpdateCases = [];
            $bedIds = array_keys($bedAssignments);
            foreach ($bedAssignments as $bedId => $studentEmail) {
                $studentId = $emailToStudentMap[ $studentEmail ] ?? null;
                if (! $studentId) {
                    continue;
                }
                $bedUpdateCases[] = "WHEN {$bedId} THEN {$studentId}";
            }

            if (! empty($bedUpdateCases)) {
                \App\Models\Bed::whereIn('id', $bedIds)->update([
                    'user_id'     => DB::raw("CASE id " . implode(' ', $bedUpdateCases) . " END"),
                    'is_occupied' => true,
                    'updated_at'  => now(),
                ]);
            }
        }
        $this->command->info('Bed assignments updated: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Add room 211 (student, no maintenance) with free beds for registration/testing
        $stepStart = microtime(true);
        $room211 = Room::firstOrCreate(
            [
                'dormitory_id'  => $adminDormitory->id,
                'number'        => '211',
            ],
            [
                'room_type_id'  => $standardRoomType->id,
                'occupant_type' => 'student',
                'floor'         => 2,
                'is_maintenance' => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]
        );
        $existingBedsCount = \App\Models\Bed::where('room_id', $room211->id)->count();
        if ($existingBedsCount === 0) {
            \App\Models\Bed::insert([
                [
                    'room_id'    => $room211->id,
                    'bed_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'room_id'    => $room211->id,
                    'bed_number' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
        $this->command->info('Room 211 (free beds for students) created: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create 5 Guests with optimized operations
        $stepStart = microtime(true);
        $guestRooms = Room::where('dormitory_id', $adminDormitory->id)
            ->where('occupant_type', 'guest')
            ->with('beds')
            ->inRandomOrder()->take(5)->get();

        $guestsData = [];
        $guestProfilesData = [];
        $bedUpdates = [];
        $guestEmails = [];

        // Pre-generate hashed password for guests
        $guestHashedPassword = Hash::make('password');

        foreach ($guestRooms as $index => $guestRoom) {
            $availableBed = $guestRoom->beds()->whereNull('user_id')->first();
            if ($availableBed) {
                assert($availableBed instanceof \App\Models\Bed);
                $firstName = $this->getFirstName(1000 + $index);
                $lastName = $this->getLastName(1000 + $index);
                $email = $this->generateUniqueEmail(1000 + $index, 'guest.com');

                $guestsData[] = [
                    'name'          => $firstName . ' ' . $lastName,
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                    'email'         => $email,
                    'phone_numbers' => json_encode([ $this->generatePhoneNumber(1000 + $index) ]),
                    'password'      => $guestHashedPassword,
                    'role_id'       => $guestRole->id,
                    'status'        => 'active',
                    'dormitory_id'  => $adminDormitory->id,
                    'room_id'       => $guestRoom->id,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];

                $guestProfilesData[] = [
                    'purpose_of_visit' => 'Business trip for conference',
                    'visit_start_date' => $this->getRandomDate('-5 days', '-1 day', $index),
                    'visit_end_date'   => $this->getRandomDate('+2 days', '+10 days', $index + 5),
                    'is_approved'      => true,
                    'bed_id'           => $availableBed->id,
                ];

                $bedUpdates[] = $availableBed->id;
                $guestEmails[] = $email;
            }
        }

        // Bulk insert guests and profiles
        if (! empty($guestsData)) {
            User::insert($guestsData);
            // Map inserted guests deterministically by email (avoids ordering/mismatch bugs)
            $insertedGuests = User::whereIn('email', $guestEmails)->get([ 'id', 'email' ]);
            $emailToGuestId = $insertedGuests->pluck('id', 'email')->toArray();

            foreach ($guestEmails as $index => $email) {
                $guestProfilesData[$index]['user_id'] = $emailToGuestId[$email] ?? null;
            }

            GuestProfile::insert($guestProfilesData);

            // Update guest beds
            \App\Models\Bed::whereIn('id', $bedUpdates)->update([
                'is_occupied' => true,
                'user_id'     => DB::raw("(SELECT user_id FROM guest_profiles WHERE bed_id = beds.id LIMIT 1)"),
                'updated_at'  => now(),
            ]);
        }
        $this->command->info('Guests created and assigned: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create 10 Messages from Admin using bulk insert
        $stepStart = microtime(true);
        $messageTitles = [
            'Welcome to the dormitory',
            'Important notice about maintenance',
            'Community event this weekend',
            'New facilities available',
            'Safety reminder',
            'Payment deadline approaching',
            'Room cleaning schedule',
            'Guest policy update',
            'Emergency contact information',
            'Study room hours'
        ];

        $messageContents = [
            'We are excited to have you join our community. Please review the dormitory rules and regulations.',
            'Scheduled maintenance will occur this weekend. Please plan accordingly.',
            'Join us for a community building event this Saturday at 3 PM in the common room.',
            'New gym and study facilities are now available for all residents.',
            'Remember to keep your doors locked and report any suspicious activity immediately.',
            'Monthly rent payments are due by the 5th of each month. Late fees will apply.',
            'Room cleaning schedule has been updated. Please check your assigned times.',
            'Guest policy has been updated. Please review the new guidelines.',
            'In case of emergency, please contact the front desk immediately.',
            'Study rooms are available 24/7. Please sign in at the front desk.'
        ];

        $messagesData = [];
        for ($i = 0; $i < 10; $i++) {
            $messagesData[] = [
                'sender_id'      => $adminUser->id,
                'receiver_id'    => null,
                'recipient_type' => 'dormitory',
                'dormitory_id'   => $adminDormitory->id,
                'title'          => $messageTitles[ $i ],
                'content'        => $messageContents[ $i ],
                'type'           => 'announcement',
                'status'         => 'sent',
                'sent_at'        => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }
        Message::insert($messagesData);
        $this->command->info('Messages created: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create all payments using optimized bulk operations
        $stepStart = microtime(true);
        $studentsForPayment = User::where('role_id', $studentRole->id)->pluck('id')->toArray();
        $guestsForPayment = GuestProfile::with('user')->get();
        $paymentsData = [];

        // Pre-generate all payment filenames for batch processing
        $paymentFilenames = [];
        // 2 payments per student (renting + catering)
        foreach ($studentsForPayment as $studentId) {
            $paymentFilenames[] = 'payment_check_student_' . $studentId . '_renting_' . uniqid() . '.png';
            $paymentFilenames[] = 'payment_check_student_' . $studentId . '_catering_' . uniqid() . '.png';
        }
        foreach ($guestsForPayment as $guest) {
            $paymentFilenames[] = 'payment_check_guest_' . $guest->user_id . '_' . uniqid() . '.png';
        }
        $this->command->info('Payment filenames generated: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Batch store all payment check images
        $stepStart = microtime(true);
        $base64PaymentImages = [
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/wcAAwAB/epv2AAAAABJRU5ErkJggg==',
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ];
        $paymentFilePaths = $this->batchStoreImages($base64PaymentImages, 'payment_checks', $paymentFilenames);
        $this->command->info('Payment check images stored: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Get payment types
        $guestStayPaymentType = \App\Models\PaymentType::where('name', 'guest_stay')->first();
        $rentingPaymentType = \App\Models\PaymentType::where('name', 'renting')->first();
        $cateringPaymentType = \App\Models\PaymentType::where('name', 'catering')->first();

        // Generate payment data
        $stepStart = microtime(true);
        $fileIndex = 0;

        // Guest payments
        foreach ($guestsForPayment as $index => $guest) {
            $paymentsData[] = [
                'user_id'        => $guest->user_id,
                'payment_type_id' => $guestStayPaymentType?->id,
                'amount'         => $this->getRandomFloat(10000, 30000, $index + 1000),
                'deal_number'    => $this->generateUniqueDealNumber(4000 + $index),
                'deal_date'      => $this->getRandomDate('-30 days', '-1 day', $index + 1000),
                'date_from'      => $guest->visit_start_date,
                'date_to'        => $guest->visit_end_date,
                'payment_check'  => $paymentFilePaths[ $fileIndex++ ],
                'status'         => PaymentStatus::Completed,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        // Student payments (renting and catering)
        foreach ($studentsForPayment as $index => $studentId) {
            // Create renting payment
            $paymentsData[] = [
                'user_id'        => $studentId,
                'payment_type_id' => $rentingPaymentType?->id,
                'amount'         => $this->getRandomFloat(250000, 350000, $index),
                'deal_number'    => $this->generateUniqueDealNumber(100000 + $index),
                'deal_date'      => $this->getRandomDate('-60 days', '-1 day', $index),
                'date_from'      => $this->getRandomDate('-90 days', '-30 days', $index),
                'date_to'        => $this->getRandomDate('+30 days', '+180 days', $index),
                'payment_check'  => $paymentFilePaths[ $fileIndex++ ] ?? null,
                'status'         => PaymentStatus::Completed,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            // Create catering payment
            $paymentsData[] = [
                'user_id'        => $studentId,
                'payment_type_id' => $cateringPaymentType?->id,
                'amount'         => 150.00,
                'deal_number'    => $this->generateUniqueDealNumber(200000 + $index),
                'deal_date'      => $this->getRandomDate('-30 days', '-1 day', $index + 100),
                'date_from'      => now()->startOfMonth()->format('Y-m-d'),
                'date_to'        => now()->endOfMonth()->format('Y-m-d'),
                'payment_check'  => $paymentFilePaths[ $fileIndex++ ] ?? null,
                'status'         => PaymentStatus::Completed,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }
        $this->command->info('Payment data generated: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Single bulk insert for all payments
        $stepStart = microtime(true);
        Payment::insert($paymentsData);
        $this->command->info('Payments inserted: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        $this->command->info("Development data seeded successfully in {$totalTime}ms!");
    }

}
