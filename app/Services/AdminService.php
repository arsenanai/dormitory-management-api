<?php

namespace App\Services;

use App\Models\AdminProfile;
use App\Models\Dormitory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminService
{
    private DormitoryService $dormitoryService;

    public function __construct()
    {
        $this->dormitoryService = new DormitoryService();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function listAdmins(): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereHas('role', function ($q) {
            $q->where('name', 'admin');
        })->with('adminProfile')
            ->with('adminDormitory')
            ->get();
    }

    /**
     * @return User
     */
    public function getAdminById(int|string $id): User
    {
        return User::whereHas('role', function ($q) {
            $q->where('name', 'admin');
        })->where('id', $id)->with('adminProfile')->with('adminDormitory')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return \App\Models\User
     */
    public function createAdmin(array $data): \App\Models\User
    {
        // Fields expected from AdminController for creation
        // Controller provides `first_name` and `last_name` separately, plus email, password, phone_numbers
        $userFields = [ 'first_name', 'last_name', 'email', 'password', 'role_id', 'phone_numbers' ];
        // Profile fields handled in admin profile
        $profileFields = [ 'position', 'department', 'office_phone', 'office_location' ];
        $userData = array_intersect_key($data, array_flip($userFields));
        $profileData = array_intersect_key($data, array_flip($profileFields));

        // Combine first_name + last_name into the user `name` column
        $first = is_string($userData['first_name'] ?? null) ? trim($userData['first_name']) : '';
        $last = is_string($userData['last_name'] ?? null) ? trim($userData['last_name']) : '';
        $combined = trim($first . ' ' . $last);
        if ($combined !== '') {
            $userData['name'] = $combined;
        }

        // Debug logging
        Log::info('AdminService::createAdmin - Input data:', $data);
        Log::info('AdminService::createAdmin - User data:', $userData);

        $password = is_string($userData['password'] ?? null) ? $userData['password'] : '';
        $userData['password'] = Hash::make($password);
        // Log the final user data that will be persisted (helps debug missing name issues)
        Log::info('AdminService::createAdmin - Final user data before persist:', $userData);
        // Use forceFill to bypass $fillable restrictions (ensure `name` is written)
        $user = new User();
        $user->forceFill($userData);
        $user->save();

        // Debug logging
        Log::info('AdminService::createAdmin - Created user:', $user->toArray());

        $profileData['user_id'] = $user->id;
        AdminProfile::create($profileData);
        $user->refresh();
        // Assign dormitory by id (controller provides `dormitory_id`)
        if (isset($data['dormitory_id']) && (is_int($data['dormitory_id']) || is_string($data['dormitory_id']))) {
            $this->dormitoryService->assignAdmin(Dormitory::findOrFail($data['dormitory_id']), $user);
        }
        return $user->load('adminProfile');
    }

    /**
     * @param  int|string  $id
     * @param  array<string, mixed>  $data
     * @return \App\Models\User
     */
    public function updateAdmin($id, array $data): \App\Models\User
    {
        $admin = User::findOrFail($id);
        // Fields expected from AdminController for update: accept first_name/last_name separately
        $userFields = [ 'first_name', 'last_name', 'email', 'password', 'role_id', 'phone_numbers' ];
        $profileFields = [ 'position', 'department', 'office_phone', 'office_location' ];
        $userData = array_intersect_key($data, array_flip($userFields));
        $profileData = array_intersect_key($data, array_flip($profileFields));

        // If first_name or last_name provided, recombine into `name` for the users table
        $first = is_string($userData['first_name'] ?? null) ? trim($userData['first_name']) : null;
        $last = is_string($userData['last_name'] ?? null) ? trim($userData['last_name']) : null;
        if ($first !== null || $last !== null) {
            // Derive missing parts from existing name if necessary
            $existingParts = preg_split('/\s+/', trim($admin->name ?? ''), 2);
            $existingFirst = $existingParts[0] ?? '';
            $existingLast = $existingParts[1] ?? '';
            $finalFirst = $first !== null ? $first : $existingFirst;
            $finalLast = $last !== null ? $last : $existingLast;
            $userData['name'] = trim($finalFirst . ' ' . $finalLast);
        }
        // Keep `first_name`/`last_name` so updates persist those columns as well

        if (isset($userData['password']) && $userData['password']) {
            $password = is_string($userData['password']) ? $userData['password'] : '';
            $userData['password'] = Hash::make($password);
        } else {
            unset($userData['password']);
        }
        // If we computed a combined `name`, set it directly on the model to ensure it's persisted
        if (isset($userData['name'])) {
            $admin->name = is_string($userData['name']) ? $userData['name'] : '';
            unset($userData['name']);
        }
        $admin->update($userData);
        if ($admin->adminProfile) {
            $admin->adminProfile->update($profileData);
        } elseif (! empty($profileData)) {
            $profileData['user_id'] = $admin->id;
            AdminProfile::create($profileData);
        }
        $admin->refresh();
        // Re-assign dormitory if provided
        if (isset($data['dormitory_id']) && (is_int($data['dormitory_id']) || is_string($data['dormitory_id']))) {
            $this->dormitoryService->assignAdmin(Dormitory::findOrFail($data['dormitory_id']), $admin);
        }
        return $admin->load('adminProfile');
    }

    /**
     * @param  int|string  $id
     */
    public function deleteAdmin($id): void
    {
        $admin = User::findOrFail($id);
        $admin->forceDelete(); // Hard delete instead of soft delete
    }
}
