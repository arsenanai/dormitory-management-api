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
use Illuminate\Support\Carbon;
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
        $names = ['john', 'jane', 'mike', 'sarah', 'alex', 'emma', 'david', 'lisa', 'james', 'mary'];
        $nameIndex = $index % count($names);
        $suffix = floor($index / count($names)) + 1;
        return "{$names[$nameIndex]}{$suffix}@{$domain}";
    }

    /**
     * Generate unique IIN using deterministic algorithm
     */
    private function generateUniqueIIN(int $index): string
    {
        $base = str_pad((string)$index, 8, '0', STR_PAD_LEFT);
        $checksum = ($index % 10);
        return $base . $checksum . str_pad((string)($index % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Generate unique deal number using deterministic algorithm
     */
    private function generateUniqueDealNumber(int $index): string
    {
        return 'DEAL-' . str_pad((string)(100000 + $index), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate deterministic phone number
     */
    private function generatePhoneNumber(int $index): string
    {
        $prefixes = ['+1', '+44', '+91', '+86', '+81', '+49', '+33', '+61'];
        $prefix = $prefixes[$index % count($prefixes)];
        $number = str_pad((string)(1000000 + ($index * 12345) % 9000000), 7, '0', STR_PAD_LEFT);
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
        return $firstNames[$index % count($firstNames)];
    }

    private function getLastName(int $index): string
    {
        $lastNames = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
            'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
            'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson'
        ];
        return $lastNames[($index + 7) % count($lastNames)];
    }

    /**
     * Get deterministic random element from array
     */
    private function getRandomElement(array $array, int $index): string
    {
        return $array[$index % count($array)];
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
            $imageData = base64_decode($base64Images[$i % count($base64Images)]);
            $path = $directory . '/' . $filename;
            Storage::disk('local')->put($path, $imageData);
            $paths[] = $path;
        }
        return $paths;
    }

    /**
     * Generate predefined student avatar images
     */
    private function generateAvatarImages(): array
    {
        return [
            // Blue 1x1 pixel
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/wcAAwAB/epv2AAAAABJRU5ErkJggg==',
            // Green 1x1 pixel
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PgAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEgAACxIB0t1+/AAAABx0RVh0U29mdHdhcmUAQWRvYmUgRmlyZXdvcmtzIENTNui8sowAAAAWdEVYdENyZWF0aW9uIFRpbWUAMDcvMTUvMTT4S9oAAABPSURBVHja7cExAQAAAMKg9U9tCF+gAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAIGSDAAB5qP1OAAAAAElFTkSuQmCC',
            // Red 1x1 pixel
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PgAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEgAACxIB0t1+/AAAABx0RVh0U29mdHdhcmUAQWRvYmUgRmlyZXdvcmtzIENTNui8sowAAAAWdEVYdENyZWF0aW9uIFRpbWUAMDcvMTUvMTT4S9oAAABPSURBVHja7cExAQAAAMKg9U9tCF+gAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAIEsPAABpQGK8AAAAAElFTkSuQmCC',
            // Purple 1x1 pixel
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PgAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEgAACxIB0t1+/AAAABx0RVh0U29mdHdhcmUAQWRvYmUgRmlyZXdvcmtzIENTNui8sowAAAAWdEVYdENyZWF0aW9uIFRpbWUAMDcvMTUvMTT4S9oAAABPSURBVHja7cExAQAAAMKg9U9tCF+gAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAALFfUbgAAW8BjhgAAAABJRU5ErkJggg==',
            // Orange 1x1 pixel
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PgAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEgAACxIB0t1+/AAAABx0RVh0U29mdHdhcmUAQWRvYmUgRmlyZXdvcmtzIENTNui8sowAAAAWdEVYdENyZWF0aW9uIFRpbWUAMDcvMTUvMTT4S9oAAABPSURBVHja7cExAQAAAMKg9U9tCF+gAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAALR9A7AAWgJX9gAAAABJRU5ErkJggg==',
        ];
    }

    public function run(): void
    {
        $startTime = microtime(true);
        $this->command->info('Starting DevelopmentSeeder...');

        // Create roles
        $stepStart = microtime(true);
        $adminRole = \App\Models\Role::firstOrCreate([ 'name' => 'admin' ]);
        $studentRole = \App\Models\Role::firstOrCreate([ 'name' => 'student' ]);
        $guestRole = \App\Models\Role::firstOrCreate(['name' => 'guest']);
        $this->command->info('Roles created: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create Room Types
        $stepStart = microtime(true);
        $standardRoomType = RoomType::firstOrCreate([ 'name' => 'standard' ], [ 'capacity' => 2, 'daily_rate' => 10000.00, 'semester_rate' => 300000.00 ]);
        $luxRoomType = RoomType::firstOrCreate([ 'name' => 'lux' ], [ 'capacity' => 1, 'daily_rate' => 20000.00, 'semester_rate' => 500000.00 ]);
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
                'name' => 'Main Admin',
                'password' => Hash::make(config('app.admin_password', 'supersecret') . ''),
                'role_id' => $adminRole->id,
                'status' => 'active',
            ]
        );
        $this->command->info('Admin user created: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create and assign the main dormitory for the admin
        $stepStart = microtime(true);
        $adminDormitory = Dormitory::firstOrCreate(
            [ 'name' => 'Main Dormitory' ],
            [
                'address' => '123 Developer Lane',
                'gender' => 'male',
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
        $this->command->info('Room data prepared: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Bulk insert rooms and get inserted IDs in one query
        $stepStart = microtime(true);
        Room::insert($roomsData);
        $rooms = Room::where('dormitory_id', $adminDormitory->id)
            ->with('roomType:id,capacity')
            ->orderBy('id')
            ->get(['id', 'room_type_id']);
        $roomMap = $rooms->keyBy('id');
        $this->command->info('Rooms inserted and fetched: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create beds for all rooms using bulk insert with pre-calculated data
        $stepStart = microtime(true);
        $bedsData = [];
        foreach ($rooms as $room) {
            $roomWithRoomType = $roomMap[$room->id] ?? null;
            if (!$roomWithRoomType) {
                continue;
            }
            $roomType = $roomWithRoomType->roomType;
            /** @var int $capacity */
            $capacity = $roomType?->capacity ?? 0;
            for ($j = 1; $j <= $capacity; $j++) {
                $bedsData[] = [
                    'bed_number' => $j,
                    'room_id' => $room->id,
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
        $bedAssignments = [];

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
            $studentFiles[$i] = [
                $allFilePaths[$fileIndex] ?? null,     // Document 1
                $allFilePaths[$fileIndex + 1] ?? null, // Document 2
                $allFilePaths[$fileIndex + 2] ?? null, // Index 2: Avatar
                null                                     // Index 3: empty
            ];
            $fileIndex += 3;
        }
        $this->command->info('Student files and avatars stored and organized: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Generate student data
        $stepStart = microtime(true);

        // Pre-define arrays for deterministic selection
        $faculties = ['Engineering', 'Business', 'Medicine', 'Law', 'Arts'];
        $specialists = ['Computer Science', 'Marketing', 'General Medicine'];
        $bloodTypesArray = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $countries = ['USA', 'Canada', 'UK', 'Germany', 'France', 'Japan', 'China', 'India', 'Brazil', 'Australia'];
        $cities = ['New York', 'London', 'Tokyo', 'Paris', 'Berlin', 'Sydney', 'Toronto', 'Mumbai', 'São Paulo', 'Beijing'];
        $emergencyTypes = ['parent', 'guardian', 'other'];

        // Pre-generate the hashed password once to avoid expensive hashing in loop
        $hashedPassword = Hash::make('password');

        // Pre-fetch bed room mappings to avoid individual queries
        $bedRoomMappings = [];
        if (!empty($availableBeds)) {
            $bedsWithRooms = \App\Models\Bed::whereIn('id', $availableBeds)
                ->with('room')
                ->get(['id', 'room_id']);
            foreach ($bedsWithRooms as $bed) {
                $bedRoomMappings[$bed->id] = $bed->room_id;
            }
        }

        for ($i = 1; $i <= 500; $i++) {
            $firstName = $this->getFirstName($i);
            $lastName = $this->getLastName($i);
            $gender = 'male';
            $email = $this->generateUniqueEmail($i);

            // Assign a bed if available
            $bedId = $availableBeds[$i - 1] ?? null;
            $roomId = $bedId ? ($bedRoomMappings[$bedId] ?? null) : null;
            if ($bedId) {
                $bedAssignments[$bedId] = $i;
            }

            $studentsData[] = [
                'name' => "$firstName $lastName",
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone_numbers' => json_encode([$this->generatePhoneNumber($i)]),
                'password' => $hashedPassword,
                'role_id' => $studentRole->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'room_id' => $roomId,
                'dormitory_id' => $roomId ? ($adminDormitory->id) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $studentProfilesData[] = [
                'iin' => $this->generateUniqueIIN($i),
                'student_id' => 'STU' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
                'faculty' => $this->getRandomElement($faculties, $i),
                'specialist' => $this->getRandomElement($specialists, $i + 3),
                'enrollment_year' => $this->getRandomNumber(2020, 2024, $i),
                'gender' => $gender,
                'blood_type' => $this->getRandomElement($bloodTypesArray, $i + 7),
                'country' => $this->getRandomElement($countries, $i + 11),
                'region' => $this->getRandomElement($cities, $i + 13),
                'city' => $this->getRandomElement($cities, $i + 17),
                'emergency_contact_name' => $this->getFirstName($i + 19) . ' ' . $this->getLastName($i + 19),
                'emergency_contact_phone' => $this->generatePhoneNumber($i + 23),
                'emergency_contact_type' => $this->getRandomElement($emergencyTypes, $i),
                'emergency_contact_email' => $this->generateUniqueEmail(2000 + $i, 'emergency.com'),
                'deal_number' => $this->generateUniqueDealNumber($i),
                'agree_to_dormitory_rules' => true,
                'has_meal_plan' => $this->getRandomBoolean($i),
                'date_of_birth' => $this->getRandomDate('-25 years', '-18 years', $i),
                'allergies' => ($i % 5 === 0) ? 'Mild pollen allergies' : null,
                'violations' => ($i % 10 === 0) ? 'Late arrival once' : null,
                'files' => json_encode($studentFiles[$i] ?? []),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        $this->command->info('Student data generated: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Bulk insert students and get IDs efficiently
        $stepStart = microtime(true);
        User::insert($studentsData);
        $insertedStudents = User::where('role_id', $studentRole->id)
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get(['id'])
            ->reverse();
        $this->command->info('Students inserted and IDs fetched: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Prepare student profiles with user_ids
        $stepStart = microtime(true);
        foreach ($insertedStudents as $index => $student) {
            $studentProfilesData[$index]['user_id'] = $student->id;
        }
        \App\Models\StudentProfile::insert($studentProfilesData);
        $this->command->info('Student profiles inserted: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Bulk update beds with user assignments using single query
        $stepStart = microtime(true);
        if (!empty($bedAssignments)) {
            $bedUpdateCases = [];
            $bedIds = array_keys($bedAssignments);
            foreach ($bedAssignments as $bedId => $studentIndex) {
                $student = $insertedStudents[$studentIndex - 1] ?? null;
                if (!$student) {
                    continue;
                }
                $studentId = $student->id;
                $bedUpdateCases[] = "WHEN {$bedId} THEN {$studentId}";
            }

            \App\Models\Bed::whereIn('id', $bedIds)->update([
                'user_id' => DB::raw("CASE id " . implode(' ', $bedUpdateCases) . " END"),
                'is_occupied' => true,
                'updated_at' => now(),
            ]);
        }
        $this->command->info('Bed assignments updated: ' . round((microtime(true) - $stepStart) * 1000, 2) . 'ms');

        // Create 5 Guests with optimized operations
        $stepStart = microtime(true);
        $guestRooms = Room::where('dormitory_id', $adminDormitory->id)
            ->where('occupant_type', 'guest')
            ->with('beds')
            ->inRandomOrder()->take(5)->get();

        $guestsData = [];
        $guestProfilesData = [];
        $bedUpdates = [];

        // Pre-generate hashed password for guests
        $guestHashedPassword = Hash::make('password');

        foreach ($guestRooms as $index => $guestRoom) {
            $availableBed = $guestRoom->beds()->whereNull('user_id')->first();
            if ($availableBed) {
                assert($availableBed instanceof \App\Models\Bed);
                $firstName = $this->getFirstName(1000 + $index);
                $lastName = $this->getLastName(1000 + $index);

                $guestsData[] = [
                    'name' => $firstName . ' ' . $lastName,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $this->generateUniqueEmail(1000 + $index, 'guest.com'),
                    'phone_numbers' => json_encode([$this->generatePhoneNumber(1000 + $index)]),
                    'password' => $guestHashedPassword,
                    'role_id' => $guestRole->id,
                    'status' => 'active',
                    'dormitory_id' => $adminDormitory->id,
                    'room_id' => $guestRoom->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $guestProfilesData[] = [
                    'purpose_of_visit' => 'Business trip for conference',
                    'visit_start_date' => $this->getRandomDate('-5 days', '-1 day', $index),
                    'visit_end_date' => $this->getRandomDate('+2 days', '+10 days', $index + 5),
                    'is_approved' => true,
                    'bed_id' => $availableBed->id,
                ];

                $bedUpdates[] = $availableBed->id;
            }
        }

        // Bulk insert guests and profiles
        if (!empty($guestsData)) {
            User::insert($guestsData);
            $insertedGuests = User::where('role_id', $guestRole->id)
                ->orderBy('id', 'desc')
                ->limit(count($guestsData))
                ->get(['id'])
                ->reverse();

            foreach ($insertedGuests as $index => $guest) {
                $guestProfilesData[$index]['user_id'] = $guest->id;
            }

            GuestProfile::insert($guestProfilesData);

            // Update guest beds
            \App\Models\Bed::whereIn('id', $bedUpdates)->update([
                'is_occupied' => true,
                'user_id' => DB::raw("(SELECT user_id FROM guest_profiles WHERE bed_id = beds.id LIMIT 1)"),
                'updated_at' => now(),
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
                'sender_id' => $adminUser->id,
                'receiver_id' => null,
                'recipient_type' => 'dormitory',
                'dormitory_id' => $adminDormitory->id,
                'title' => $messageTitles[$i],
                'content' => $messageContents[$i],
                'type' => 'announcement',
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
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
        foreach ($studentsForPayment as $studentId) {
            $paymentFilenames[] = 'payment_check_student_' . $studentId . '_' . uniqid() . '.png';
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

        // Generate payment data
        $stepStart = microtime(true);
        $fileIndex = 0;

        // First semester payments for students
        foreach ($studentsForPayment as $index => $studentId) {
            $paymentsData[] = [
                'user_id' => $studentId,
                'amount' => $this->getRandomFloat(50000, 150000, $index),
                'deal_number' => $this->generateUniqueDealNumber(2000 + $index),
                'deal_date' => $this->getRandomDate('-30 days', '-1 day', $index),
                'date_from' => Carbon::parse('2025-01-01'),
                'date_to' => Carbon::parse('2025-06-01'),
                'payment_check' => $paymentFilePaths[$fileIndex++],
                'status' => PaymentStatus::Completed,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Second semester payments for students
        foreach ($studentsForPayment as $index => $studentId) {
            $paymentsData[] = [
                'user_id' => $studentId,
                'amount' => $this->getRandomFloat(50000, 150000, $index + 500),
                'deal_number' => $this->generateUniqueDealNumber(3000 + $index),
                'deal_date' => $this->getRandomDate('-30 days', '-1 day', $index + 500),
                'date_from' => Carbon::parse('2025-09-01'),
                'date_to' => Carbon::parse('2026-01-01'),
                'payment_check' => $paymentFilePaths[$fileIndex++],
                'status' => PaymentStatus::Completed,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Guest payments
        foreach ($guestsForPayment as $index => $guest) {
            $paymentsData[] = [
                'user_id' => $guest->user_id,
                'amount' => $this->getRandomFloat(10000, 30000, $index + 1000),
                'deal_number' => $this->generateUniqueDealNumber(4000 + $index),
                'deal_date' => $this->getRandomDate('-30 days', '-1 day', $index + 1000),
                'date_from' => $guest->visit_start_date,
                'date_to' => $guest->visit_end_date,
                'payment_check' => $paymentFilePaths[$fileIndex++],
                'status' => PaymentStatus::Completed,
                'created_at' => now(),
                'updated_at' => now(),
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
