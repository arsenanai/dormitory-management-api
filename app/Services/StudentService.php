<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\Dormitory;
use App\Models\Role;
use App\Models\Room;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentService
{
    public function __construct(private ConfigurationService $configurationService)
    {
    }

    /**
     * Validate a student file at specific index
     *
     * @param mixed $file The file to validate
     * @param int $index The file index (0, 1, or 2)
     * @return array Validation result with 'valid' boolean and 'message' string
     */
    public function validateStudentFile($file, int $index): array
    {
        // Skip validation for string values (existing file paths)
        if (is_string($file)) {
            return [ 'valid' => true, 'message' => null ];
        }

        if (! $file instanceof UploadedFile) {
            return [ 'valid' => true, 'message' => null ];
        }

        // Different validation rules for different file indices
        $rules = $this->getFileValidationRules($index);

        $validator = Validator::make(
            [ 'file' => $file ],
            [ 'file' => $rules ]
        );

        return [
            'valid'   => $validator->passes(),
            'message' => $validator->fails() ? $validator->errors()->first('file') : null
        ];
    }

    /**
     * Get validation rules for file at specific index
     *
     * @param int $index The file index
     * @return array Validation rules
     */
    private function getFileValidationRules(int $index): array
    {
        switch ($index) {
            case 0: // Document 1
            case 1: // Document 2
                return [
                    'mimetypes:image/jpg,image/jpeg,image/png,application/pdf,application/octet-stream',
                    'max:2048'
                ];
            case 2: // Avatar photo
                return [
                    'mimetypes:image/jpg,image/jpeg,image/png',
                    'max:1024',
                    'dimensions:min_width=150,min_height=200,max_width=600,max_height=800'
                ];
            default:
                return [ 'mimetypes:image/jpg,image/jpeg,image/png,application/pdf,application/octet-stream|max:2048' ];
        }
    }

    /**
     * Construct full name from first and last name
     *
     * @param array $data Data containing first_name and last_name
     * @return string The constructed full name
     */
    public function constructFullName(array $data): string
    {
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');

        return trim($firstName . ' ' . $lastName);
    }

    /**
     * Log file information for debugging
     *
     * @param int $index File index
     * @param UploadedFile $file The uploaded file
     * @return void
     */
    public function logFileInfo(int $index, UploadedFile $file): void
    {
        Log::info('Debugging student file ' . $index, [
            'original_name' => $file->getClientOriginalName(),
            'extension'     => $file->getClientOriginalExtension(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
            'is_valid'      => $file->isValid(),
        ]);
    }
    /**
     * Get students with filters and pagination
     */
    public function getStudentsWithFilters(User $authUser, array $filters = [])
    {
        return $this->buildStudentQuery($authUser, $filters, true);
    }

    /**
     * Create a new student
     */
    public function updateStudent($id, array $data, User $authUser)
    {
        return DB::transaction(function () use ($id, $data, $authUser) {
            $warning = null;
            $student = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
                ->with([ 'studentBed', 'studentProfile' ]) // Eager load relationships for efficiency
                ->findOrFail($id);

            // If a new bed/room is being assigned, validate gender compatibility with the new dormitory.
            if (! empty($data['bed_id'])) {
                $newBed = Bed::with('room.dormitory')->find($data['bed_id']);
                if ($newBed && $newBed->room && $newBed->room->dormitory) {
                    /** @var \App\Models\Dormitory $newDormitory */
                    $newDormitory = $newBed->room->dormitory;
                    /** @var string $dormitoryGender */
                    $dormitoryGender = $newDormitory->gender;
                    $studentGender = $data['gender'] ?? ($student->studentProfile->gender ?? 'male');
                    if (
                        ($dormitoryGender === 'male' && $studentGender === 'female') ||
                        ($dormitoryGender === 'female' && $studentGender === 'male')
                    ) {
                        throw ValidationException::withMessages([ 'bed_id' => 'The selected dormitory does not accept students of this gender.' ]);
                    }
                }
            }

            // Handle bed assignment and get the new room_id if it changes
            $oldRoomId = $student->room_id;
            $oldRoomTypeId = $student->room?->room_type_id;

            $newRoomId = $this->processBedAssignment($student, isset($data['bed_id']) ? (int) $data['bed_id'] : null, $authUser->hasRole('sudo'));
            if ($newRoomId !== false) { // `false` indicates no change
                $data['room_id'] = $newRoomId;

                // If the student is assigned to a new room (not just unassigned)
                if ($newRoomId !== null) {
                    // Update room_id immediately so payment calculation uses the new room
                    $student->room_id = $newRoomId;
                    $student->save();

                    $student->load([ 'room.roomType', 'role' ]);
                    $newRoomTypeId = $student->room?->room_type_id;

                    // Only trigger payment when room type actually changes (e.g. standard ↔ lux)
                    if ($oldRoomTypeId && $newRoomTypeId && $oldRoomTypeId !== $newRoomTypeId) {
                        if ($student->hasPaidSemesterRentPayment()) {
                            $warning = 'This student has paid for the current semester. The previous payment will not be cancelled or refunded. They should contact dormitory administration.';
                        }
                        $student->createPaymentsForTriggerEvent('room_type_change');
                    }
                }
            }

            // Prepare data for User and StudentProfile models
            $userData = $this->prepareUserData($data, $student);
            $dormitoryId = $authUser->adminDormitory->id ?? $student->dormitory_id;
            if ($dormitoryId) {
                $userData['dormitory_id'] = $dormitoryId;
            }
            $profileData = $this->prepareProfileData($data, true);

            // Only process file uploads if new files are included in the student_profile.
            if (isset($data['student_profile']['files'])) {
                Log::info('StudentService: Processing file uploads.', $data['student_profile']['files']);
                // Process files and get the final array of paths. This must be done
                // before the main profile update to ensure the correct paths are saved.
                $processedFilePaths = $this->processFileUploads($data['student_profile'], $student->studentProfile ?? null);
                // Explicitly set the processed files array on the profile data.
                $profileData['files'] = $processedFilePaths;
            }

            // Update the User model with user-specific data.
            if (! empty($userData)) {
                $student->update($userData);
            }

            // Update the StudentProfile model with profile-specific data.
            if (! empty($profileData) && $student->studentProfile) {
                // Ensure student_id is not accidentally nulled out on update
                if ($student->studentProfile->student_id) {
                    /** @var \App\Models\StudentProfile $profile */
                    $profile = $student->studentProfile;
                    /** @var string $studentId */
                    $studentId = $profile->student_id;
                    $profileData['student_id'] = $studentId;
                }
                $student->studentProfile->update($profileData);
            }

            $user = $student->fresh()->load([ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ]);

            return [ 'user' => $user, 'warning' => $warning ];
        });
    }

    /**
     * Get student details
     */
    public function getStudentDetails($id)
    {
        $student = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->with([
                'role',
                'studentProfile',
                'room.beds',
                'room.dormitory',
                'room.roomType',
                'studentBed',
                'payments' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ])
            ->findOrFail($id);

        return $student; // Return User model
    }

    /**
     * Delete student
     */
    public function deleteStudent($id)
    {
        $student = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->with('studentProfile', 'studentBed') // Eager load for cleanup
            ->findOrFail($id);

        // Delete associated files from StudentProfile
        if ($student->studentProfile && ! empty($student->studentProfile->files)) {
            $filesToDelete = $student->studentProfile->files;
            // If files are stored as a JSON string, decode it first.
            if (is_string($filesToDelete)) {
                $filesToDelete = json_decode($filesToDelete, true) ?? [];
            }
            $this->deleteFiles(is_array($filesToDelete) ? $filesToDelete : []);
        }

        // Free up the bed if assigned
        if ($student->studentBed) {
            $student->studentBed->update([ 'user_id' => null ]);
        }

        $student->studentProfile->delete();
        $student->delete();
        return true; // Return boolean for success
    }

    /**
     * Approve student application
     */
    public function approveStudent($id)
    {
        $student = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->findOrFail($id);

        $oldStatus = $student->status;
        $student->status = 'active';
        $student->save();

        event(new \App\Events\MailEventOccurred('user.status_changed', [
            'user' => $student, 'old_status' => $oldStatus, 'new_status' => 'active',
        ]));

        return $student->load([ 'role', 'studentProfile', 'room' ]);
    }

    /**
     * Export students to CSV
     */
    public function exportStudents(User $authUser, array $filters = [])
    {
        // Use the same query logic as getStudentsWithFilters for consistency, but don't paginate
        $query = $this->buildStudentQuery($authUser, $filters, false);

        $students = $query->get();

        // Define which columns to export based on the request, defaulting to table columns
        $defaultCols = [ 'name', 'status', 'enrollment_year', 'faculty', 'dormitory', 'bed', 'phone' ];
        $exportCols = isset($filters['columns']) ? explode(',', $filters['columns']) : $defaultCols;

        $headers = array_map(function ($col) {
            $map = [
                'name'            => 'Name',
                'status'          => 'Status',
                'enrollment_year' => 'Enrollment year',
                'faculty'         => 'Faculty',
                'dormitory'       => 'Dormitory',
                'bed'             => 'Bed',
                'phone'           => 'Telephone',
                'iin'             => 'IIN',
                'created_at'      => 'Registered date'
            ];
            return $map[ $col ] ?? ucfirst(str_replace('_', ' ', $col));
        }, $exportCols);
        $csvContent = implode(',', $headers) . "\n";

        foreach ($students as $student) {
            $rowData = [];
            foreach ($exportCols as $col) {
                $value = '';
                switch ($col) {
                    case 'name':
                        $value = $student->name;
                        break;
                    case 'status':
                        $value = $student->status;
                        break;
                    case 'enrollment_year':
                        $value = $student->studentProfile->enrollment_year ?? '';
                        break;
                    case 'faculty':
                        $value = $student->studentProfile->faculty ?? '';
                        break;
                    case 'dormitory':
                        $value = $student->room->dormitory->name ?? '';
                        break;
                    case 'bed':
                        $value = $student->room ? ($student->room->number . '-' . ($student->studentBed->bed_number ?? '')) : '';
                        break;
                    case 'phone':
                        $value = is_array($student->phone_numbers) ? implode(';', $student->phone_numbers) : ($student->phone ?? '');
                        break;
                    case 'iin':
                        $value = $student->studentProfile->iin ?? '';
                        break;
                    case 'created_at':
                        $value = $student->created_at ? $student->created_at->format('Y-m-d H:i:s') : '';
                        break;
                        // Add other cases for any other potential columns
                }
                // Escape quotes for CSV
                $rowData[] = '"' . str_replace('"', '""', $value) . '"';
            }
            $csvContent .= implode(',', $rowData) . "\n";
        }

        $filename = 'students_export_' . date('Y-m-d_H-i-s') . '.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function buildStudentQuery(User $authUser, array $filters = [], bool $paginate = true)
    {
        $query = User::whereHas('role', fn ($q) => $q->where('name', 'student'))->with([ 'role', 'studentProfile', 'room.dormitory', 'room.roomType', 'studentBed' ]);

        // Filter by the admin's dormitory if the user is an admin or if the flag is set
        if (
            ($filters['my_dormitory_only'] ?? false) ||
            ($authUser->hasRole('admin') && ! $authUser->hasRole('sudo') && $authUser->adminDormitory)
        ) {
            if ($authUser->adminDormitory) {
                /** @var \App\Models\Dormitory $adminDorm */
                $adminDorm = $authUser->adminDormitory;
                /** @var int $dormitoryId */
                $dormitoryId = $adminDorm->id;
                $query->where('dormitory_id', $dormitoryId);
            }
        }

        // Apply other filters
        if (isset($filters['faculty'])) {
            $query->whereHas('studentProfile', function ($q) use ($filters) {
                $q->where('faculty', 'like', '%' . $filters['faculty'] . '%');
            });
        }

        if (isset($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply search query across multiple fields
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('studentProfile', function ($sq) use ($search) {
                        $sq->where('faculty', 'like', "%{$search}%")
                            ->orWhere('iin', 'like', "%{$search}%");
                    })
                    ->orWhereHas('room', function ($rq) use ($search) {
                        $rq->where('number', 'like', "%{$search}%");
                    });
            });
        }

        if ($paginate) {
            $perPage = $filters['per_page'] ?? 20;
            return $query->paginate($perPage);
        }

        return $query;
    }

    /**
     * Get students with filters and pagination
     */
    public function createStudent(array $data, Dormitory $dormitory)
    {
        \Log::info('StudentService: createStudent method started.', $data);

        return DB::transaction(function () use ($data, $dormitory) {
            // Validate that the student's gender is compatible with the dormitory's gender policy.
            $gender = $data['gender'] ?? null;
            if (
                ($dormitory->gender === 'male' && $gender === 'female') ||
                ($dormitory->gender === 'female' && $gender === 'male')
            ) {
                throw ValidationException::withMessages([ 'room_id' => 'The selected dormitory does not accept students of this gender.' ]);
            }

            // Prepare data for User and StudentProfile models
            $userData = $this->prepareUserData($data);
            $profileData = $this->prepareProfileData($data, false);

            // The 'files' array is already validated and part of $data['student_profile'].
            // We just need to store them and get their paths.
            if (isset($profileData['files']) && is_array($profileData['files'])) {
                $storedFilePaths = $this->storeNewFiles($profileData['files']);
                $profileData['files'] = $storedFilePaths;
            }
            // If a bed is assigned, update the user's room_id and dormitory_id
            // if (isset($data['bed_id'])) {
            // 	$bed = Bed::with('room')->find($data['bed_id']);
            // 	if ($bed) {
            // 		$userData['room_id'] = $bed->room_id;
            // 		$userData['dormitory_id'] = $bed->room->dormitory_id;
            // 	}
            // } else {
            $userData['dormitory_id'] = $dormitory->id;
            // }
            // Create the User
            $student = User::create($userData);

            // Create the StudentProfile
            $profileData['user_id'] = $student->id;
            $profileData['deal_number'] = date('Y') . '-' . strtoupper(\Illuminate\Support\Str::random(8));
            StudentProfile::create($profileData);

            // Assign bed if provided
            $newRoomId = $this->processBedAssignment($student, $data['bed_id'] ?? null, false);
            if ($newRoomId) {
                $student->room_id = $newRoomId;
                $student->save();
            }

            $student->load([ 'role', 'studentProfile', 'room.roomType', 'room.dormitory', 'studentBed' ]);
            if ($student->room_id) {
                $student->createPaymentsForTriggerEvent('registration');
            }

            $locale = $this->normalizeMailLocale($data['locale'] ?? null);
            event(new \App\Events\MailEventOccurred('user.registered', [ 'user' => $student, 'locale' => $locale ]));

            return $student->load([ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ]);
        });
    }

    /**
     * Get students by dormitory
     */
    public function getStudentsByDormitory(User $authUser, array $filters = [])
    {
        $query = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->with([ 'role', 'studentProfile', 'room', 'room.dormitory' ]);
        if (optional($authUser->role)->name === 'admin' && ! isset($filters['dormitory_id'])) {
            // Get dormitory_id from AdminProfile relationship
            $adminDormitoryId = $authUser->adminProfile?->dormitory_id;
            if ($adminDormitoryId) {
                $filters['dormitory_id'] = (int) $adminDormitoryId;
            }
        }

        // Apply filters
        if (isset($filters['dormitory_id'])) {
            $query->whereHas('room', function ($q) use ($filters) {
                $q->where('dormitory_id', $filters['dormitory_id']);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 20;

        $students = $query->paginate($perPage);

        return $students; // Return Paginator instance
    }

    /**
     * Get unassigned students
     */
    public function getUnassignedStudents(array $filters = [])
    {
        $query = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->whereNull('room_id')
            ->with([ 'role', 'studentProfile' ]);

        // Apply filters
        if (isset($filters['faculty'])) {
            $query->whereHas('studentProfile', function ($q) use ($filters) {
                $q->where('faculty', 'like', '%' . $filters['faculty'] . '%');
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 20;

        $students = $query->paginate($perPage);

        return $students; // Return Paginator instance
    }

    /**
     * Update student access
     */
    public function updateStudentAccess($id, array $data)
    {
        $student = User::whereHas('role', fn ($q) => $q->where('name', 'student'))
            ->findOrFail($id);

        $oldStatus = $student->status;
        if (isset($data['has_access'])) {
            $student->status = $data['has_access'] ? 'active' : 'suspended';
        }
        $student->save();

        if (isset($data['has_access']) && $oldStatus !== $student->status) {
            event(new \App\Events\MailEventOccurred('user.status_changed', [
                'user' => $student, 'old_status' => $oldStatus, 'new_status' => $student->status,
            ]));
        }

        return $student->load([ 'role', 'room', 'studentProfile' ]);
    }

    /**
     * Get student statistics
     */
    public function getStudentStatistics(User $authUser, array $filters = [])
    {
        $query = User::whereHas('role', fn ($q) => $q->where('name', 'student'));

        // Apply filters first, then default to admin's dormitory if no filter is set
        if (isset($filters['dormitory_id'])) {
            $query->where('dormitory_id', $filters['dormitory_id']);
        } elseif ($authUser->hasRole('admin') && $authUser->adminDormitory) {
            /** @var \App\Models\Dormitory $adminDorm */
            $adminDorm = $authUser->adminDormitory;
            /** @var int $dormitoryId */
            $dormitoryId = $adminDorm->id;
            $query->where('dormitory_id', $dormitoryId);
        }

        if (isset($filters['faculty'])) {
            $query->whereHas('studentProfile', function ($q) use ($filters) {
                $q->where('faculty', 'like', '%' . $filters['faculty'] . '%');
            });
        }

        $stats = [
            'total'     => $query->count(),
            'active'    => (clone $query)->where('status', 'active')->count(),
            'pending'   => (clone $query)->where('status', 'pending')->count(),
            'suspended' => (clone $query)->where('status', 'suspended')->count(),
        ];

        return $stats; // Return array
    }

    /**
     * Calculate the semester fee for a student.
     *
     * // TODO: meal calculation will change
     * @return float Calculated semester fee.
     */
    public function calculateSemesterFee(): float
    {
        return 0;
    }

    /**
     * Delete files from storage
     */
    private function deleteFiles(array $files)
    {
        // Delete up to 3 files
        foreach ($files as $file) {
            // Ensure the file path is a valid, non-empty string and exists before attempting to delete.
            if (is_string($file) && ! empty($file) && Storage::disk('public')->exists($file)) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * Handles the logic for assigning, re-assigning, or un-assigning a bed for a student.
     *
     * @param User $student The student user instance.
     * @param int|null $newBedId The ID of the new bed, or null to un-assign.
     * @return int|null|false Returns the new room_id on change, null if unassigned, or false if no change occurred.
     * @param bool $isSudo
     * @throws ValidationException
     */
    private function processBedAssignment(User $student, ?int $newBedId, bool $isSudo = false)
    {
        $oldBed = $student->studentBed()->first();
        /** @var \App\Models\Bed|null $oldBed */
        $oldBedId = $oldBed->id ?? null;

        if ($newBedId === $oldBedId) {
            return false; // No change in bed assignment.
        }

        // Free up the old bed if it exists.
        if ($oldBed) {
            $oldBed->user_id = null;
            $oldBed->is_occupied = false;
            $oldBed->save();
        }

        // If a new bed is being assigned.
        if ($newBedId) {
            $newBed = Bed::find($newBedId);

            if (! $newBed) {
                throw ValidationException::withMessages([ 'bed_id' => 'Selected bed does not exist.' ]);
            }
            if ($newBed->reserved_for_staff) {
                throw ValidationException::withMessages([ 'bed_id' => 'This bed is reserved for staff.' ]);
            }
            if ($newBed->is_occupied && $newBed->user_id !== $student->id && ! $isSudo) {
                throw ValidationException::withMessages([ 'bed_id' => 'Selected bed is already occupied.' ]);
            }

            // Assign the new bed.
            $newBed->user_id = $student->id;
            $newBed->is_occupied = true;
            $newBed->save();

            return $newBed->room_id; // Return new room_id
        }

        return null; // Return null as the student is now unassigned from any room.
    }

    /**
     * Prepares the data array for creating or updating a User.
     */
    private function prepareUserData(array $data, ?User $user = null): array
    {
        $userFillable = array_merge((new User())->getFillable(), [ 'first_name', 'last_name' ]);
        $userData = array_intersect_key($data, array_flip($userFillable));

        if ($user) { // Update
            if (! empty($data['password'])) {
                // log that password is being updated
                $userData['password'] = Hash::make($data['password']);
            } else {
                unset($userData['password']);
            }
            if (isset($userData['first_name']) || isset($userData['last_name'])) {
                $userData['name'] = trim(($userData['first_name'] ?? $user->first_name) . ' ' . ($userData['last_name'] ?? $user->last_name));
            }
        } else { // Create
            $userData['password'] = Hash::make($data['password']);
            $userData['status'] = 'pending';
            $userData['role_id'] = Role::where('name', 'student')->first()->id ?? 3;
            if (isset($data['first_name']) && isset($data['last_name'])) {
                $userData['name'] = $this->constructFullName($data);
            } elseif (isset($data['name'])) {
                // Split the name into first and last names if not provided separately
                $nameParts = explode(' ', trim($data['name']), 2);
                $userData['first_name'] = $nameParts[0];
                $userData['last_name'] = $nameParts[1] ?? null;
            }
        }

        unset($userData['dormitory_id']); // Dormitory is derived from the room.
        return $userData;
    }

    /**
     * Prepares the data array for the StudentProfile.
     */
    private function prepareProfileData(array $data, bool $isUpdate): array
    {
        $profileFillable = (new StudentProfile())->getFillable();
        // Prioritize the nested student_profile object if it exists, otherwise use the flat data array.
        $sourceData = $data['student_profile'] ?? $data;
        $profileData = array_intersect_key($sourceData, array_flip($profileFillable));

        // Ensure student_id is correctly handled for both create and update.
        // The `student_id` might be at the root of `$data` or inside `student_profile`.
        if ($isUpdate) {
            // On update, prioritize the `student_id` from the root if it exists.
            $profileData['student_id'] = $data['student_id'] ?? $profileData['student_id'] ?? null;
        } else {
            // On create, it could be in either place. Fallback to IIN if not present.
            $profileData['student_id'] = $data['student_id'] ?? $profileData['student_id'] ?? $profileData['iin'] ?? null;
        }

        // Explicitly cast boolean-like strings to a boolean for database insertion.
        if (isset($profileData['agree_to_dormitory_rules'])) {
            $profileData['agree_to_dormitory_rules'] = filter_var($profileData['agree_to_dormitory_rules'], FILTER_VALIDATE_BOOLEAN);
        }

        return $profileData;
    }

    /**
     * Handles file uploads and deletion for a StudentProfile.
     */
    private function processFileUploads(array $data, ?StudentProfile $profile): array
    {
        $incomingFiles = $data['files'] ?? null;

        // If no 'files' key is in the request, assume no changes are being made to files.
        if (! is_array($incomingFiles)) {
            \Log::info('StudentService: No files found in profile data for processing.');
            return $profile ? ($profile->files ?? []) : [];
        }

        // Start with the existing file paths. This handles "unchanged" files by default.
        $filePaths = ($profile && is_array($profile->files)) ? $profile->files : array_fill(0, 4, null);

        foreach ($incomingFiles as $index => $file) {
            $oldFile = $filePaths[ $index ] ?? null;

            if ($file instanceof \Illuminate\Http\UploadedFile) {
                // New file uploaded: delete the old one and store the new one.
                if ($oldFile && Storage::disk('public')->exists($oldFile)) {
                    Storage::disk('public')->delete($oldFile);
                }
                $filePaths[ $index ] = $this->storeStudentFile($file);
            } elseif (is_string($file) && ! empty($file)) {
                // An existing file path was sent back, indicating "no change".
                $filePaths[ $index ] = $file;
            } elseif ($file === null || $file === '') {
                // File marked for removal: delete the old file and set path to null.
                if ($oldFile && Storage::disk('public')->exists($oldFile)) {
                    Storage::disk('public')->delete($oldFile);
                }
                $filePaths[ $index ] = null;
            }
        }
        // Ensure the array has 4 elements, preserving order.
        return array_replace(array_fill(0, 3, null), $filePaths);
    }

    /**
     * Stores an array of new files and returns their paths.
     * This is used during student creation.
     *
     * @param array $files The array of files from the request.
     * @return array The array of stored file paths, preserving keys.
     */
    private function storeNewFiles(array $files): array
    {
        $storedPaths = array_fill(0, 3, null);
        foreach ($files as $index => $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $storedPaths[ $index ] = $this->storeStudentFile($file);
            }
        }
        return $storedPaths;
    }

    public function listAllStudents(): \Illuminate\Database\Eloquent\Collection
    {
        $authUser = auth()->user();
        if (! $authUser) {
            return User::whereRaw('1 = 0')->get();
        }
        $query = User::select([ 'id', 'name', 'email' ])
            ->where('role_id', Role::where('name', 'student')->firstOrFail()->id);

        // Sudo can see all students. Admin can only see students from their assigned dormitory.
        if ($authUser->hasRole('admin') && ! $authUser->hasRole('sudo')) {
            /** @var \App\Models\Dormitory|null $adminDormitory */
            $adminDormitory = $authUser->adminDormitory()->first();
            if ($adminDormitory) {
                $query->where('dormitory_id', $adminDormitory->id);
            } else {
                // Admin with no dormitory assigned sees no students.
                return User::whereRaw('1 = 0')->get();
            }
        }
        return $query
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Stores a student file, preserving original name if possible.
     * If a file with the original name exists, it generates a short unique name.
     *
     * @param \Illuminate\Http\UploadedFile $file The file to store.
     * @return string The path to stored file.
     */
    private function storeStudentFile(\Illuminate\Http\UploadedFile $file): string
    {
        $originalFilename = $file->getClientOriginalName();
        $storagePath = 'avatars/' . $originalFilename;

        if (Storage::disk('public')->exists($storagePath)) {
            // File exists, generate a short unique name
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '_' . \Illuminate\Support\Str::random(6) . '.' . $ext;
        } else {
            // File does not exist, use original name.
            $filename = $originalFilename;
        }
        return $file->storeAs('avatars', $filename, 'public');
    }

    /**
     * Check if email is available for registration.
     *
     * @param string $email
     * @param int|null $ignoreUserId
     * @return bool
     */
    public function checkEmailAvailability(string $email, ?int $ignoreUserId = null): bool
    {
        $query = User::withTrashed()->where('email', $email);

        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return ! $query->exists();
    }

    public function checkIinAvailability(string $iin, ?int $ignoreUserId = null): bool
    {
        $query = StudentProfile::where('iin', $iin);

        if ($ignoreUserId) {
            $query->whereHas('user', fn ($q) => $q->where('id', '!=', $ignoreUserId));
        }
        return ! $query->exists();
    }

    private function normalizeMailLocale(mixed $value): string
    {
        $s = is_string($value) ? strtolower(trim($value)) : '';
        if ($s === 'kz') {
            $s = 'kk';
        }
        return in_array($s, [ 'en', 'kk', 'ru' ], true) ? $s : 'en';
    }
}
