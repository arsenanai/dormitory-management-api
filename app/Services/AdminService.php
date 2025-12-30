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
    public function listAdmins()
    {
        // Only list users with the 'admin' role (exclude 'sudo')
        return User::whereHas('role', function ($q) {
            $q->where('name', 'admin');
        })->with('adminProfile')
            ->with('adminDormitory')
            ->get();
    }

    public function getAdminById($id)
    {
        // Get a specific admin by ID with their profile
        return User::whereHas('role', function ($q) {
            $q->where('name', 'admin');
        })->where('id', $id)->with('adminProfile')->with('adminDormitory')->firstOrFail();
    }

    public function createAdmin(array $data)
    {
        // Fields expected from AdminController for creation
        // Controller provides `first_name` and `last_name` separately, plus email, password, phone_numbers
        $userFields = [ 'first_name', 'last_name', 'email', 'password', 'role_id', 'phone_numbers' ];
        // Profile fields handled in admin profile
        $profileFields = [ 'position', 'department', 'office_phone', 'office_location' ];
        $userData = array_intersect_key($data, array_flip($userFields));
        $profileData = array_intersect_key($data, array_flip($profileFields));

        // Combine first_name + last_name into the user `name` column
        $first = isset($userData['first_name']) ? trim($userData['first_name']) : '';
        $last = isset($userData['last_name']) ? trim($userData['last_name']) : '';
        $combined = trim($first . ' ' . $last);
        if ($combined !== '') {
            $userData['name'] = $combined;
        }

        // Debug logging
        Log::info('AdminService::createAdmin - Input data:', $data);
        Log::info('AdminService::createAdmin - User data:', $userData);

        $userData['password'] = Hash::make($userData['password']);
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
        if (isset($data['dormitory_id'])) {
            $this->dormitoryService->assignAdmin(Dormitory::findOrFail($data['dormitory_id']), $user);
        }
        return $user->load('adminProfile');
    }

    public function updateAdmin($id, array $data)
    {
        $admin = User::findOrFail($id);
        // Fields expected from AdminController for update: accept first_name/last_name separately
        $userFields = [ 'first_name', 'last_name', 'email', 'password', 'role_id', 'phone_numbers' ];
        $profileFields = [ 'position', 'department', 'office_phone', 'office_location' ];
        $userData = array_intersect_key($data, array_flip($userFields));
        $profileData = array_intersect_key($data, array_flip($profileFields));

        // If first_name or last_name provided, recombine into `name` for the users table
        $first = isset($userData['first_name']) ? trim($userData['first_name']) : null;
        $last = isset($userData['last_name']) ? trim($userData['last_name']) : null;
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
            $userData['password'] = Hash::make($userData['password']);
        } else {
            unset($userData['password']);
        }
        // If we computed a combined `name`, set it directly on the model to ensure it's persisted
        if (isset($userData['name'])) {
            $admin->name = $userData['name'];
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
        if (isset($data['dormitory_id'])) {
            $this->dormitoryService->assignAdmin(Dormitory::findOrFail($data['dormitory_id']), $admin);
        }
        return $admin->load('adminProfile');
    }

    public function deleteAdmin($id)
    {
        $admin = User::findOrFail($id);
        $admin->forceDelete(); // Hard delete instead of soft delete
        // Return a key so controller or caller can translate
        return response()->json([ 'message' => 'success.admin_deleted' ], 200);
    }
}
