<?php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DormitoryController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\SemesterPaymentController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;

// Public routes
Route::post( '/login', [ UserController::class, 'login' ] );
Route::post( '/register', [ UserController::class, 'register' ] );

// Protected routes
Route::middleware( [ 'auth:sanctum' ] )->group( function () {

	// Dashboard routes
	Route::get( '/dashboard', [ DashboardController::class, 'index' ] );
	Route::get( '/dashboard/dormitory/{dormitoryId}', [ DashboardController::class, 'dormitoryStats' ] );

	// Student routes (for authenticated users to see their own data)
	Route::get( '/my-messages', [ MessageController::class, 'myMessages' ] );
	Route::post( '/messages/{id}/read', [ MessageController::class, 'markAsRead' ] );
	Route::get( '/users/profile', [ UserController::class, 'profile' ] );
	Route::put( '/users/profile', [ UserController::class, 'updateProfile' ] );
	Route::put( '/users/change-password', [ UserController::class, 'changePassword' ] );

	// Admin and sudo routes
	Route::middleware( [ 'role:admin,sudo' ] )->group( function () {

		// User management
		Route::apiResource( 'users', UserController::class);

		// Student management
		Route::get( '/students/export', [ StudentController::class, 'export' ] );
		Route::apiResource( 'students', StudentController::class);
		Route::patch( '/students/{id}/approve', [ StudentController::class, 'approve' ] );

		// Guest management
		Route::get( '/guests/export', [ GuestController::class, 'export' ] );
		Route::get( '/guests/available-rooms', [ GuestController::class, 'availableRooms' ] );
		Route::post( '/guests/{id}/check-out', [ GuestController::class, 'checkOut' ] );
		Route::apiResource( 'guests', GuestController::class);

		// Payment management
		Route::get( '/payments/export', [ PaymentController::class, 'export' ] );
		Route::apiResource( 'payments', PaymentController::class);

		// Semester payment management
		Route::get( '/semester-payments/stats', [ SemesterPaymentController::class, 'getStats' ] );
		Route::get( '/semester-payments/users-with-access', [ SemesterPaymentController::class, 'getUsersWithAccess' ] );
		Route::post( '/semester-payments/create-for-all-students', [ SemesterPaymentController::class, 'createForAllStudents' ] );
		Route::post( '/semester-payments/{semesterPayment}/approve-payment', [ SemesterPaymentController::class, 'approvePayment' ] );
		Route::post( '/semester-payments/{semesterPayment}/reject-payment', [ SemesterPaymentController::class, 'rejectPayment' ] );
		Route::post( '/semester-payments/{semesterPayment}/approve-dormitory', [ SemesterPaymentController::class, 'approveDormitoryAccess' ] );
		Route::post( '/semester-payments/{semesterPayment}/reject-dormitory', [ SemesterPaymentController::class, 'rejectDormitoryAccess' ] );
		Route::apiResource( 'semester-payments', SemesterPaymentController::class);

		// Message management
		Route::apiResource( 'messages', MessageController::class);
		Route::post( '/messages/{id}/send', [ MessageController::class, 'send' ] );

		// Room management (admins can manage rooms in their dormitory)
		Route::apiResource( 'rooms', RoomController::class);
	} );

	// Sudo-only routes
	Route::middleware( [ 'role:sudo' ] )->group( function () {

		// Admin management
		Route::apiResource( 'admins', AdminController::class);

		// Dormitory management
		Route::apiResource( 'dormitories', DormitoryController::class);
		Route::post( 'dormitories/{dormitory}/assign-admin', [ DormitoryController::class, 'assignAdmin' ] );

		// Room type management
		Route::apiResource( 'room-types', RoomTypeController::class);
	} );
} );