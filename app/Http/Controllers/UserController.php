<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\GuestService;
use App\Services\StudentService;
use App\Services\UserAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

use function response;

class UserController extends Controller
{
    protected $authService;
    protected $studentService;
    protected $guestService;

    private array $adminRegisterRules = [
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|max:255|unique:users,email',
        'password' => 'required|string|min:6|confirmed',
    ];

    private array $guestRegisterRules = [
        'first_name'               => 'required|string|max:255',
        'last_name'                => 'required|string|max:255',
        'email'                    => 'required|email|max:255|unique:users,email',
        'phone'                    => 'required|string|max:20',
        'password'                 => 'required|string|min:6|confirmed',
        'agree_to_dormitory_rules' => 'required|accepted',
        'gender'                   => 'required|in:male,female',
        'check_in_date'            => 'required|date',
        'check_out_date'           => 'required|date|after:check_in_date',
        'dormitory_id'             => 'required|integer|exists:dormitories,id',
        'room_type_id'             => 'required|integer|exists:room_types,id',
        'room_id'                  => 'required|integer|exists:rooms,id',
        'bed_id'                   => 'required|integer|exists:beds,id',
        'total_amount'             => 'nullable|numeric|min:0',
        'bank_paycheck'            => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        'notes'                    => 'nullable|string', // Purpose of visit
        'host_name'                => 'nullable|string|max:255',
        'host_contact'             => 'nullable|string|max:255',
        'reminder'                 => 'nullable|string',
        'identification_type'      => 'nullable|string|max:255',
        'identification_number'    => 'nullable|string|max:255',
        'emergency_contact_name'   => 'nullable|string|max:255',
        'emergency_contact_phone'  => 'nullable|string|max:255',
        'locale'                   => 'nullable|string|in:en,kk,ru,kz',
    ];

    public function __construct(UserAuthService $authService, StudentService $studentService, GuestService $guestService)
    {
        $this->authService = $authService;
        $this->studentService = $studentService;
        $this->guestService = $guestService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        $result = $this->authService->attemptLogin($request->email, $request->password);
        // Debug logging
        \Log::info('Login attempt', [
            'email'        => $request->email,
            'result_type'  => gettype($result),
            'result_value' => $result === 'not_approved' ? 'not_approved' : ($result === null ? 'null' : 'user_object')
        ]);

        if ($result === 'not_approved') {
            return response()->json([ 'message' => 'auth.not_approved' ], 401);
        }

        if ($result === 'not_assigned_admin') {
            return response()->json([ 'message' => 'auth.not_assigned_admin' ], 401);
        }

        if (! $result) {
            return response()->json([ 'message' => 'auth.invalid_credentials' ], 401);
        }

        // Load appropriate relationships based on user role
        if ($result->role && $result->role->name === 'admin') {
            // Load the correct relationship that already exists on the User model
            $result->load([ 'role', 'adminDormitory' ]);
        } elseif ($result->role && $result->role->name === 'student') {
            $result->load([ 'role', 'studentProfile' ]);
        } elseif ($result->role && $result->role->name === 'guest') {
            $result->load([ 'role', 'guestProfile' ]);
        }

        $token = $result->createToken('user-token')->plainTextToken;

        return response()->json([
            'user'  => $result->toArray() + [
                // Ensure a consistent 'dormitory' property for both admins and students
                'dormitory' => $result->role->name === 'admin' ? $result->adminDormitory : $result->dormitory,
            ],
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        // if( isset( $request->student_profile )
        // 	&& isset( $request->student_profile['files'] )
        //     && is_array( $request->student_profile['files'] ) ) {
        // 	foreach($request->student_profile['files'] as $index => $file) {
        // 		if( isset( $file ) && $file instanceof UploadedFile ) {
        // 			Log::info('Debugging student file ' . $index, [
        // 				'original_name' => $file->getClientOriginalName(),
        // 				'extension' => $file->getClientOriginalExtension(),
        // 				'mime_type' => $file->getMimeType(),
        // 				'size' => $file->getSize(),
        // 				'is_valid' => $file->isValid(),
        // 			]);
        // 		}
        // 	}
        // }

        $userType = $request->input('user_type', 'student');
        if ($userType === 'admin') {
            $rules = $this->adminRegisterRules;
        } elseif ($userType === 'guest') {
            $rules = $this->guestRegisterRules;
            $validatedData = $request->validate($rules);

            // The GuestService will be used for creation.
            $guest = $this->guestService->createGuest($validatedData);

            return response()->json([
                // t('Registration successful. Please log in and make due payments.')
                'message' => 'Registration successful. Please log in and make due payments.',
                'data'    => $guest->load([ 'guestProfile', 'role', 'room.dormitory' ])
            ], 201);
        } else {
            // Handle nested student_profile payload by merging it into the root request
            // $data = $request->all();
            // if (isset($data['student_profile']) && is_array($data['student_profile'] ) ) {
            // 	$request->merge($data['student_profile']);
            // }

            // Manually construct the 'name' field from first_name and last_name before validation.
            if ($request->has('first_name') && $request->has('last_name') && ! $request->has('name')) {
                $request->merge([ 'name' => trim($request->input('first_name') . ' ' . $request->input('last_name')) ]);
            }

            $validatedData = $request->validate([
                'bed_id'                                   => 'required|integer|exists:beds,id',
                'dormitory_id'                             => 'required|integer|exists:dormitories,id',
                'email'                                    => 'required|email|max:255|unique:users,email',
                'first_name'                               => 'required|string|max:255',
                'last_name'                                => 'required|string|max:255',
                'name'                                     => 'required|string|max:255',
                'password'                                 => 'required|string|min:6|confirmed',
                'phone_numbers.*'                          => 'string',
                'phone_numbers'                            => 'nullable|array',
                'room_id'                                  => 'required|integer|exists:rooms,id',
                'student_profile.agree_to_dormitory_rules' => 'required|accepted',
                'student_profile.allergies'                => 'nullable|string|max:1000',
                'student_profile.blood_type'               => 'nullable|string',
                'student_profile.city'                     => 'nullable|string|max:255',
                'student_profile.country'                  => 'nullable|string|max:255',
                'student_profile.emergency_contact_name'   => 'nullable|string|max:255',
                'student_profile.emergency_contact_phone'  => 'nullable|string|max:255',
                'student_profile.emergency_contact_type'   => 'nullable|in:parent,guardian,other',
                'student_profile.emergency_contact_email'  => 'nullable|email|max:255',
                'student_profile.identification_type'      => 'required|string|in:passport,national_id,drivers_license,other',
                'student_profile.identification_number'    => 'required|string|max:255',
                'student_profile.enrollment_year'          => 'required|integer|digits:4',
                'student_profile.faculty'                  => 'required|string|max:255',
                'student_profile.specialist'               => 'required|string|max:255',
                'student_profile.iin'                      => 'required|digits:12|unique:student_profiles,iin',
                'student_profile.region'                   => 'nullable|string|max:255',
                'student_profile.files'                    => 'sometimes|nullable|array|max:4',
                'student_profile.files.*'                  => [
                    'nullable',
                    function ($attribute, $value, $fail) {
                        if ($value instanceof UploadedFile) {
                            $validator = \Illuminate\Support\Facades\Validator::make(
                                [ $attribute => $value ],
                                [ $attribute => 'mimetypes:image/jpg,image/jpeg,image/png,application/pdf,application/octet-stream|max:2048' ]
                            );
                            if ($validator->fails()) {
                                $fail($validator->errors()->first($attribute));
                            }
                        }
                    },
                ],
                'student_profile.gender'                   => 'required|in:male,female',
                'student_profile.violations'               => 'nullable|string|max:1000',
                'locale'                                   => 'nullable|string|in:en,kk,ru,kz',
            ]);

            // The service expects a Dormitory object.
            $dormitory = \App\Models\Dormitory::find($validatedData['dormitory_id']);

            if (! $dormitory) {
                return response()->json([ 'message' => 'Invalid or missing dormitory information.' ], 422);
            }

            // Ensure boolean fields are correctly casted, as they might come from FormData.
            $validatedData['student_profile']['agree_to_dormitory_rules'] = filter_var($validatedData['student_profile']['agree_to_dormitory_rules'] ?? false, FILTER_VALIDATE_BOOLEAN);

            // The createStudent method in StudentService now handles all the logic.
            // We just need to pass it the validated and normalized data.
            $student = $this->studentService->createStudent($validatedData, $dormitory);

            // Return a success response
            return response()->json([
                // t('Registration successful. Please log in and make due payments.')
                'message' => 'Registration successful. Please log in and make due payments.',
                'data'    => $student->load([ 'studentProfile', 'role', 'room.dormitory', 'studentBed' ])
            ], 201);
        }
    }

    /**
     * Display a listing of users (admin only)
     */
    public function index(Request $request)
    {
        $admin = $request->user();
        $query = User::with([ 'role', 'room.dormitory' ])
            ->when($request->search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%");
                });
            })
            ->when($request->role, function ($query, $role) {
                return $query->whereHas('role', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            })
            ->when($admin->hasRole('admin'), function ($query) use ($admin) {
                if ($admin->adminDormitory) {
                    $query->where('dormitory_id', $admin->adminDormitory->id);
                }
            });
        $users = $query->paginate(15);

        return response()->json($users);
    }

    /**
     * Store a newly created user (admin only)
     */
    public function store(Request $request)
    {
        $rules = [
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'email'             => 'required|email|unique:users,email',
            'password'          => 'required|string|min:6',
            'role_id'           => 'required|exists:roles,id',
            'phone'             => 'nullable|string|max:20',
            'status'            => 'nullable|in:pending,approved,rejected',
            'dormitory_id'      => 'nullable|exists:dormitories,id',

            // Student-specific fields
            'student_id'        => 'nullable|string|max:20|unique:users,student_id',
            'iin'               => 'nullable|string|max:12',
            'birth_date'        => 'nullable|date',
            'date_of_birth'     => 'nullable|date',
            'blood_type'        => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'course'            => 'nullable|string|max:100',
            'faculty'           => 'nullable|string|max:100',
            'specialty'         => 'nullable|string|max:100',
            'enrollment_year'   => 'nullable|integer|min:1900|max:' . date('Y'),
            'graduation_year'   => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'year_of_study'     => 'nullable|integer|min:1|max:6',
            'gender'            => 'nullable|in:male,female',
            'emergency_contact' => 'nullable|string|max:100',
            'emergency_phone'   => 'nullable|string|max:20',
            'violations'        => 'nullable|string',
            'deal_number'       => 'nullable|string|max:255',
            'country'           => 'nullable|string|max:255',
            'region'            => 'nullable|string|max:255',
            'city'              => 'nullable|string|max:255',
        ];

        $validated = $request->validate($rules);
        $validated['password'] = Hash::make($validated['password']);

        // Generate name from first_name and last_name
        $validated['name'] = $validated['first_name'] . ' ' . $validated['last_name'];

        // Always set admin role_id if user_type is admin
        if ($request->input('user_type') === 'admin') {
            $validated['role_id'] = Role::where('name', 'admin')->first()->id ?? 1;
        }

        // Reject admin creation attempts here
        if ($request->input('user_type') === 'admin') {
            return response()->json([ 'message' => 'Admin creation is not allowed via this endpoint.' ], 403);
        }

        // Handle phone numbers as array and also store in phone column
        if (isset($validated['phone'])) {
            $validated['phone_numbers'] = [ $validated['phone'] ];
            // Keep phone in the phone column as well
        }

        // Handle date_of_birth mapping to birth_date
        if (isset($validated['date_of_birth'])) {
            $validated['birth_date'] = $validated['date_of_birth'];
            unset($validated['date_of_birth']);
        }

        if (! isset($validated['status'])) {
            $validated['status'] = 'approved';
        }

        $user = User::create($validated);

        // Create profile records based on role
        if ($user->hasRole('student')) {
            // Create StudentProfile
            $studentProfileData = [
                'user_id'                  => $user->id,
                'iin'                      => $validated['iin'] ?? '000000000000', // Default IIN if not provided
                'student_id'               => $validated['student_id'] ?? 'STU' . str_pad($user->id, 6, '0', STR_PAD_LEFT), // Default student_id if not provided
                'faculty'                  => $validated['faculty'] ?? null,
                'specialist'               => $validated['specialty'] ?? null,
                'enrollment_year'          => $validated['enrollment_year'] ?? null,
                'gender'                   => $validated['gender'] ?? 'other',
                'blood_type'               => $validated['blood_type'] ?? null,
                'emergency_contact_name'   => $validated['emergency_contact'] ?? null,
                'emergency_contact_phone'  => $validated['emergency_phone'] ?? null,
                'violations'               => $validated['violations'] ?? null,
                'deal_number'              => $validated['deal_number'] ?? null,
                'city'                     => $validated['city'] ?? null,
                'course'                   => $validated['course'] ?? null,
                'year_of_study'            => $validated['year_of_study'] ?? null,
                'agree_to_dormitory_rules' => true,
                'files'                    => json_encode([]),
            ];
            \App\Models\StudentProfile::create($studentProfileData);
        } elseif ($user->hasRole('guest')) {
            // Create GuestProfile
            $guestProfileData = [
                'user_id'                 => $user->id,
                'purpose_of_visit'        => $validated['purpose_of_visit'] ?? null,
                'host_name'               => $validated['host_name'] ?? null,
                'host_contact'            => $validated['host_contact'] ?? null,
                'visit_start_date'        => $validated['visit_start_date'] ?? null,
                'visit_end_date'          => $validated['visit_end_date'] ?? null,
                'identification_type'     => $validated['identification_type'] ?? null,
                'identification_number'   => $validated['identification_number'] ?? null,
                'emergency_contact_name'  => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'is_approved'             => $validated['is_approved'] ?? false,
                'daily_rate'              => $validated['daily_rate'] ?? null,
            ];
            \App\Models\GuestProfile::create($guestProfileData);
        }

        if ($user->hasRole('student') || $user->hasRole('guest')) {
            event(new \App\Events\MailEventOccurred('user.registered', [
                'user'   => $user->load([ 'role', 'studentProfile', 'guestProfile' ]),
                'locale' => 'en',
            ]));
        }

        return response()->json($user->load([ 'role', 'room.dormitory', 'adminDormitory', 'studentProfile', 'guestProfile' ]), 201);
    }

    /**
     * Display the specified user (admin only)
     */
    public function show(User $user)
    {
        return response()->json($user->load([ 'role', 'room.dormitory', 'room' ]));
    }

    /**
     * Update the specified user (admin only)
     */
    public function update(Request $request, User $user)
    {
        $rules = [
            'first_name'              => 'sometimes|string|max:255',
            'last_name'               => 'sometimes|string|max:255',
            'email'                   => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'password'                => 'sometimes|string|min:6',
            'role_id'                 => 'sometimes|exists:roles,id',
            'phone'                   => 'nullable|string|max:20',
            'status'                  => 'sometimes|in:pending,approved,rejected',
            'dormitory_id'            => 'nullable|exists:dormitories,id',
            // Student-specific fields
            'student_id'              => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('student_profiles', 'student_id')->ignore(optional($user->studentProfile)->id),
            ],
            'iin'                     => 'nullable|string|max:12',
            'faculty'                 => 'nullable|string|max:100',
            'specialist'              => 'nullable|string|max:100',
            'enrollment_year'         => 'nullable|integer|min:1900|max:' . date('Y'),
            'gender'                  => 'nullable|in:male,female',
            'blood_type'              => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'course'                  => 'nullable|string|max:100',
            'year_of_study'           => 'nullable|integer|min:1|max:6',
            'emergency_contact'       => 'nullable|string|max:100',
            'emergency_phone'         => 'nullable|string|max:20',
            'violations'              => 'nullable|string',
            'deal_number'             => 'nullable|string|max:255',
            'country'                 => 'nullable|string|max:255',
            'region'                  => 'nullable|string|max:255',
            'city'                    => 'nullable|string|max:255',
            'files'                   => 'nullable|array|max:4',
            'files.*'                 => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
            // Guest-specific fields
            'purpose_of_visit'        => 'nullable|string|max:255',
            'host_name'               => 'nullable|string|max:255',
            'host_contact'            => 'nullable|string|max:255',
            'visit_start_date'        => 'nullable|date',
            'visit_end_date'          => 'nullable|date',
            'identification_type'     => 'nullable|string|max:255',
            'identification_number'   => 'nullable|string|max:255',
            'emergency_contact_name'  => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',
            'is_approved'             => 'nullable|boolean',
            'daily_rate'              => 'nullable|numeric',
        ];

        $validated = $request->validate($rules);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Update name if first_name or last_name changed
        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $firstName = $validated['first_name'] ?? $user->first_name;
            $lastName = $validated['last_name'] ?? $user->last_name;
            $validated['name'] = $firstName . ' ' . $lastName;
        }

        // Handle phone numbers as array and also store in phone column
        if (isset($validated['phone'])) {
            $validated['phone_numbers'] = [ $validated['phone'] ];
        }

        // Split user and profile fields
        $userFields = [ 'first_name', 'last_name', 'name', 'email', 'password', 'role_id', 'phone_numbers', 'room_id', 'status', 'dormitory_id', 'iin' ];
        $profileFields = array_diff(array_keys($validated), $userFields);
        $userData = array_intersect_key($validated, array_flip($userFields));
        $profileData = array_intersect_key($validated, array_flip($profileFields));

        $oldStatus = $user->status;
        $user->update($userData);
        if (($user->hasRole('student') || $user->hasRole('guest')) && isset($userData['status']) && $oldStatus !== $user->status) {
            event(new \App\Events\MailEventOccurred('user.status_changed', [
                'user' => $user->fresh([ 'role' ]), 'old_status' => $oldStatus, 'new_status' => $user->status,
            ]));
        }

        // Update profile if student or guest
        if ($user->hasRole('student')) {
            // Map profile fields correctly
            $studentProfileData = [];
            if (isset($profileData['faculty'])) {
                $studentProfileData['faculty'] = $profileData['faculty'];
            }
            if (isset($profileData['specialist'])) {
                $studentProfileData['specialist'] = $profileData['specialist'];
            }
            if (isset($profileData['enrollment_year'])) {
                $studentProfileData['enrollment_year'] = $profileData['enrollment_year'];
            }
            if (isset($profileData['gender'])) {
                $studentProfileData['gender'] = $profileData['gender'];
            }
            if (isset($profileData['blood_type'])) {
                $studentProfileData['blood_type'] = $profileData['blood_type'];
            }
            if (isset($profileData['course'])) {
                $studentProfileData['course'] = $profileData['course'];
            }
            if (isset($profileData['year_of_study'])) {
                $studentProfileData['year_of_study'] = $profileData['year_of_study'];
            }
            if (isset($profileData['emergency_contact'])) {
                $studentProfileData['emergency_contact_name'] = $profileData['emergency_contact'];
            }
            if (isset($profileData['emergency_phone'])) {
                $studentProfileData['emergency_contact_phone'] = $profileData['emergency_phone'];
            }
            if (isset($profileData['violations'])) {
                $studentProfileData['violations'] = $profileData['violations'];
            }
            if (isset($profileData['deal_number'])) {
                $studentProfileData['deal_number'] = $profileData['deal_number'];
            }
            if (isset($profileData['iin'])) {
                $studentProfileData['iin'] = $profileData['iin'];
            }
            if (isset($profileData['student_id'])) {
                $studentProfileData['student_id'] = $profileData['student_id'];
            }

            if ($user->studentProfile) {
                $user->studentProfile->update($studentProfileData);
            } else {
                // Create StudentProfile if it doesn't exist
                $studentProfileData['user_id'] = $user->id;
                $studentProfileData['iin'] = $studentProfileData['iin'] ?? '000000000000'; // Default IIN if not provided
                $studentProfileData['student_id'] = $studentProfileData['student_id'] ?? 'STU' . str_pad($user->id, 6, '0', STR_PAD_LEFT); // Default student_id if not provided
                $studentProfileData['agree_to_dormitory_rules'] = true;
                $studentProfileData['files'] = json_encode([]);
                $studentProfileData['gender'] = $studentProfileData['gender'] ?? 'other'; // Default gender if not provided
                \App\Models\StudentProfile::create($studentProfileData);
            }
        } elseif ($user->hasRole('guest')) {
            if ($user->guestProfile) {
                $user->guestProfile->update($profileData);
            } else {
                // Create GuestProfile if it doesn't exist
                $profileData['user_id'] = $user->id;
                \App\Models\GuestProfile::create($profileData);
            }
        }

        return response()->json($user->load([ 'role', 'room.dormitory', 'studentProfile', 'guestProfile' ]));
    }

    /**
     * Remove the specified user (soft delete)
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([ 'message' => 'User deleted successfully' ]);
    }

    /**
     * Get current user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        // Return role-specific profile data
        if ($user->hasRole('student')) {
            return response()->json($user->load([ 'studentProfile', 'role', 'room.dormitory', 'room.dormitory.admin', 'studentBed' ]));
        } elseif ($user->hasRole('guest')) {
            // You can create a GuestResource for consistency here as well
            return response()->json($user->load([ 'role', 'room.dormitory', 'room', 'guestProfile', 'room.dormitory.admin', 'guestProfile.bed' ]));
        } else {
            // For admin and other roles, return basic user information
            $user->load([ 'role', 'adminDormitory', 'adminProfile' ]);
            return response()->json([
                'id'            => $user->id,
                'name'          => $user->name,
                'first_name'    => $user->first_name,
                'last_name'     => $user->last_name,
                'email'         => $user->email,
                'phone_numbers' => $user->phone_numbers,
                'role'          => $user->role,
                'dormitory'     => $user->adminDormitory, // This is the assigned dormitory for an admin
                'admin_profile' => $user->adminProfile,
                'status'        => $user->status,
                'created_at'    => $user->created_at,
                'updated_at'    => $user->updated_at,
            ]);
        }
    }

    /**
     * Return full personal data for the authenticated student or guest.
     */
    public function personalData(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('student')) {
            $user->load([ 'role', 'studentProfile', 'room.dormitory', 'studentBed' ]);
            return response()->json($user);
        }

        if ($user->hasRole('guest')) {
            $user->load([ 'role', 'guestProfile', 'room.dormitory', 'guestProfile.bed' ]);
            return response()->json($user);
        }

        return response()->json([ 'message' => 'Access denied' ], 403);
    }

    /**
     * Update personal data for the authenticated student or guest.
     */
    public function updatePersonalData(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('student')) {
            $rules = [
                'bed_id'                                         => 'nullable|exists:beds,id',
                'email'                                          => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'first_name'                                     => 'sometimes|string|max:255',
                'last_name'                                      => 'sometimes|string|max:255',
                'name'                                           => 'sometimes|string|max:255',
                'password'                                       => 'nullable|string|min:6|confirmed',
                'phone_numbers.*'                                => 'string',
                'phone_numbers'                                  => 'nullable|array',
                'room_id'                                        => 'nullable|exists:rooms,id',
                'room_type'                                      => 'nullable|string|exists:room_types,name',
                'student_profile.agree_to_dormitory_rules'       => 'nullable|boolean',
                'student_profile.allergies'                      => 'nullable|string|max:1000',
                'student_profile.blood_type'                     => [ 'nullable', 'string' ],
                'student_profile.city'                           => 'nullable|string|max:255',
                'student_profile.country'                        => 'nullable|string|max:255',
                'student_profile.deal_number'                    => 'nullable|string|max:255',
                'student_profile.emergency_contact_name'         => 'nullable|string|max:255',
                'student_profile.emergency_contact_phone'        => 'nullable|string|max:255',
                'student_profile.emergency_contact_type'         => 'nullable|in:parent,guardian,other',
                'student_profile.emergency_contact_email'        => 'nullable|email|max:255',
                'student_profile.emergency_contact_relationship' => 'nullable|string|max:255',
                'student_profile.enrollment_year'                => 'nullable|integer|digits:4',
                'student_profile.faculty'                        => 'nullable|string|max:255',
                'student_profile.files'                          => 'nullable|array|max:4',
                'student_profile.files.*'                        => [
                    'nullable',
                    function ($attribute, $value, $fail) {
                        if ($value instanceof UploadedFile) {
                            $validator = \Illuminate\Support\Facades\Validator::make(
                                [ $attribute => $value ],
                                [ $attribute => 'mimetypes:image/jpg,image/jpeg,image/png,application/pdf,application/octet-stream|max:2048' ]
                            );
                            if ($validator->fails()) {
                                $fail($validator->errors()->first($attribute));
                            }
                        }
                    },
                ],
                'student_profile.gender'                         => 'nullable|in:male,female',
                'student_profile.iin'                            => [
                    'sometimes',
                    'digits:12',
                    Rule::unique('student_profiles', 'iin')->ignore(optional($user->studentProfile)->id),
                ],
                'student_profile.region'                         => 'nullable|string|max:255',
                'student_profile.specialist'                     => 'nullable|string|max:255',
                'student_profile.student_id'                     => [
                    'sometimes',
                    'string',
                    'max:255',
                    Rule::unique('student_profiles', 'student_id')->ignore(optional($user->studentProfile)->id),
                ],
                'student_profile.violations'                     => 'nullable|string|max:1000',
            ];

            $validated = $request->validate($rules);
            $result = $this->studentService->updateStudent($user->id, $validated, $user);

            return response()->json([
                'data' => $result['user'],
                'warning' => $result['warning'],
            ]);
        }

        if ($user->hasRole('guest')) {
            $rules = [
                'first_name'              => 'sometimes|string|max:255',
                'last_name'               => 'sometimes|string|max:255',
                'email'                   => [
                    'sometimes',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'phone'                   => 'sometimes|string|max:20',
                'room_id'                 => 'nullable|integer|exists:rooms,id',
                'bed_id'                  => 'nullable|integer|exists:beds,id',
                'room_type'               => 'nullable|string|exists:room_types,name',
                'check_in_date'           => 'nullable|date',
                'check_out_date'          => 'nullable|date|after_or_equal:check_in_date',
                'notes'                   => 'nullable|string',
                'host_name'               => 'nullable|string|max:255',
                'host_contact'            => 'nullable|string|max:255',
                'identification_type'     => 'nullable|string|max:255',
                'identification_number'   => 'nullable|string|max:255',
                'emergency_contact_name'  => 'nullable|string|max:255',
                'emergency_contact_phone' => 'nullable|string|max:255',
                'reminder'                => 'nullable|string',
            ];

            $validated = $request->validate($rules);
            $result = $this->guestService->updateGuest($user->id, $validated);

            return response()->json([
                'data' => $result['user'],
                'warning' => $result['warning'],
            ]);
        }

        return response()->json([ 'message' => 'Access denied' ], 403);
    }

    /**
     * Update current user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $rules = [
            'first_name'              => 'sometimes|string|max:255',
            'last_name'               => 'sometimes|string|max:255',
            'email'                   => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'phone'                   => 'nullable|string|max:20',
            'phone_numbers'           => 'sometimes|nullable|array',
            'phone_numbers.*'         => 'sometimes|nullable|string|max:20',
            'dormitory_id'            => 'nullable|exists:dormitories,id',
            // Student-specific fields
            'iin'                     => 'nullable|string|max:12',
            'faculty'                 => 'nullable|string|max:100',
            'specialist'              => 'nullable|string|max:100',
            'enrollment_year'         => 'nullable|integer|min:1900|max:' . date('Y'),
            'gender'                  => 'nullable|in:male,female',
            'blood_type'              => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'course'                  => 'nullable|string|max:100',
            'year_of_study'           => 'nullable|integer|min:1|max:6',
            'emergency_contact'       => 'nullable|string|max:100',
            'emergency_phone'         => 'nullable|string|max:20',
            'violations'              => 'nullable|string',
            'deal_number'             => 'nullable|string|max:255',
            'country'                 => 'nullable|string|max:255',
            'region'                  => 'nullable|string|max:255',
            'city'                    => 'nullable|string|max:255',
            'files'                   => 'nullable|array|max:4',
            'files.*'                 => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
            // Guest-specific fields
            'purpose_of_visit'        => 'nullable|string|max:255',
            'host_name'               => 'nullable|string|max:255',
            'host_contact'            => 'nullable|string|max:255',
            'visit_start_date'        => 'nullable|date',
            'visit_end_date'          => 'nullable|date',
            'identification_type'     => 'nullable|string|max:255',
            'identification_number'   => 'nullable|string|max:255',
            'emergency_contact_name'  => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',
            'is_approved'             => 'nullable|boolean',
            'daily_rate'              => 'nullable|numeric',
        ];
        $validated = $request->validate($rules);
        unset($validated['role_id'], $validated['status']);
        // Update name if first_name or last_name changed
        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $firstName = $validated['first_name'] ?? $user->first_name;
            $lastName = $validated['last_name'] ?? $user->last_name;
            $validated['name'] = $firstName . ' ' . $lastName;
        }
        // Handle phone numbers as array and also store in phone column
        if (isset($validated['phone'])) {
            $validated['phone_numbers'] = [ $validated['phone'] ];
        }
        // Split user and profile fields
        $userFields = [ 'first_name', 'last_name', 'name', 'email', 'phone_numbers', 'room_id', 'dormitory_id', 'iin' ];
        $profileFields = array_diff(array_keys($validated), $userFields);
        $userData = array_intersect_key($validated, array_flip($userFields));
        $profileData = array_intersect_key($validated, array_flip($profileFields));
        $user->update($userData);
        // Update profile if student or guest
        if ($user->hasRole('student')) {
            // Map profile fields correctly
            $studentProfileData = [];
            if (isset($profileData['faculty'])) {
                $studentProfileData['faculty'] = $profileData['faculty'];
            }
            if (isset($profileData['specialist'])) {
                $studentProfileData['specialist'] = $profileData['specialist'];
            }
            if (isset($profileData['enrollment_year'])) {
                $studentProfileData['enrollment_year'] = $profileData['enrollment_year'];
            }
            if (isset($profileData['gender'])) {
                $studentProfileData['gender'] = $profileData['gender'];
            }
            if (isset($profileData['blood_type'])) {
                $studentProfileData['blood_type'] = $profileData['blood_type'];
            }
            if (isset($profileData['course'])) {
                $studentProfileData['course'] = $profileData['course'];
            }
            if (isset($profileData['year_of_study'])) {
                $studentProfileData['year_of_study'] = $profileData['year_of_study'];
            }
            if (isset($profileData['emergency_contact'])) {
                $studentProfileData['emergency_contact_name'] = $profileData['emergency_contact'];
            }
            if (isset($profileData['emergency_phone'])) {
                $studentProfileData['emergency_contact_phone'] = $profileData['emergency_phone'];
            }
            if (isset($profileData['violations'])) {
                $studentProfileData['violations'] = $profileData['violations'];
            }
            if (isset($profileData['deal_number'])) {
                $studentProfileData['deal_number'] = $profileData['deal_number'];
            }
            if (isset($profileData['iin'])) {
                $studentProfileData['iin'] = $profileData['iin'];
            }
            if (isset($profileData['student_id'])) {
                $studentProfileData['student_id'] = $profileData['student_id'];
            }

            if ($user->studentProfile) {
                $user->studentProfile->update($studentProfileData);
            } else {
                // Create StudentProfile if it doesn't exist
                $studentProfileData['user_id'] = $user->id;
                $studentProfileData['iin'] = $studentProfileData['iin'] ?? '000000000000'; // Default IIN if not provided
                $studentProfileData['student_id'] = $studentProfileData['student_id'] ?? 'STU' . str_pad($user->id, 6, '0', STR_PAD_LEFT); // Default student_id if not provided
                $studentProfileData['agree_to_dormitory_rules'] = true;
                $studentProfileData['files'] = json_encode([]);
                $studentProfileData['gender'] = $studentProfileData['gender'] ?? 'other'; // Default gender if not provided
                \App\Models\StudentProfile::create($studentProfileData);
            }
        } elseif ($user->hasRole('guest')) {
            if ($user->guestProfile) {
                $user->guestProfile->update($profileData);
            } else {
                // Create GuestProfile if it doesn't exist
                $profileData['user_id'] = $user->id;
                \App\Models\GuestProfile::create($profileData);
            }
        } elseif ($user->hasRole('admin') || $user->hasRole('sudo')) {
            // Handle admin profile updates
            if ($user->adminProfile) {
                $user->adminProfile->update($profileData);
            } else {
                // Create AdminProfile if it doesn't exist
                $profileData['user_id'] = $user->id;
                \App\Models\AdminProfile::create($profileData);
            }
        }
        return response()->json($user->load([ 'role', 'room.dormitory', 'studentProfile', 'guestProfile', 'adminProfile' ]));
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:6|confirmed',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors'  => [ 'current_password' => [ 'The current password is incorrect.' ] ]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password'])
        ]);

        return response()->json([ 'message' => 'Password updated successfully' ]);
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([ 'message' => 'Logged out successfully' ]);
    }

    /**
     * Send password reset link to user's email
     */
    public function sendPasswordResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            // Don't reveal if email exists or not for security
            return response()->json([
                'message' => 'If this email exists in our system, you will receive a password reset link.'
            ]);
        }

        // Generate a password reset token
        $token = \Str::random(64);

        // Store in password_resets table (create migration if needed)
        \DB::table('password_resets')->updateOrInsert(
            [ 'email' => $user->email ],
            [
                'email'      => $user->email,
                'token'      => Hash::make($token),
                'created_at' => now()
            ]
        );

        // Send password reset email
        \Mail::to($user->email)->send(new \App\Mail\PasswordResetMail($token, $user->email));

        return response()->json([
            'message'     => 'If this email exists in our system, you will receive a password reset link.',
            'debug_token' => $token // Remove this in production
        ]);
    }

    /**
     * Reset password using token
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:6|confirmed'
        ]);

        $passwordReset = \DB::table('password_resets')
            ->where('email', $request->email)
            ->first();

        if (! $passwordReset || ! Hash::check($request->token, $passwordReset->token)) {
            return response()->json([
                'message' => 'Invalid or expired password reset token.'
            ], 422);
        }

        // Check if token is not older than 60 minutes
        if (now()->diffInMinutes($passwordReset->created_at) > 60) {
            return response()->json([
                'message' => 'Password reset token has expired.'
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'User not found.'
            ], 404);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Delete the used token
        \DB::table('password_resets')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Password has been reset successfully.'
        ]);
    }

    public function checkEmailAvailability(Request $request)
    {
        $request->validate([
            'email'          => 'required|email|max:255',
            'ignore_user_id' => 'sometimes|integer|exists:users,id', // Optional: for editing profile
        ]);

        $isAvailable = $this->studentService->checkEmailAvailability($request->email, $request->ignore_user_id);

        return response()->json([ 'is_available' => $isAvailable ]);
    }

    public function checkIinAvailability(Request $request)
    {
        $request->validate([
            'iin'            => 'required|digits:12',
            'ignore_user_id' => 'sometimes|integer|exists:users,id',
        ]);

        $isAvailable = $this->studentService->checkIinAvailability($request->iin, $request->ignore_user_id);

        return response()->json([ 'is_available' => $isAvailable ]);
    }

    /**
     * API endpoint: GET /users/{id}/can-access-dormitory or /me/can-access-dormitory
     * Returns: { can_access: boolean, reason: string }
     */
    public function canAccessDormitory(Request $request, $id = null)
    {
        $user = $id ? User::findOrFail($id) : $request->user();
        $canAccess = $user->canAccessDormitory();
        $reason = $canAccess ? 'Access granted' : 'Access denied: payment or dormitory approval missing';
        return response()->json([
            'can_access' => $canAccess,
            'reason'     => $reason,
        ]);
    }
}
