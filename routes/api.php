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

	// Dashboard routes - role-specific access
	Route::middleware( [ 'role:admin,sudo' ] )->group( function () {
		Route::get( '/dashboard', [ DashboardController::class, 'index' ] );
		Route::get( '/dashboard/stats', [ DashboardController::class, 'index' ] );
		Route::get( '/dashboard/dormitory/{dormitoryId}', [ DashboardController::class, 'dormitoryStats' ] );
		Route::get( '/dashboard/monthly-stats', [ DashboardController::class, 'monthlyStats' ] );
		Route::get( '/dashboard/payment-analytics', [ DashboardController::class, 'paymentAnalytics' ] );
	} );

	Route::middleware( [ 'role:guard' ] )->group( function () {
		Route::get( '/dashboard/guard', [ DashboardController::class, 'guardDashboard' ] );
	} );

	Route::middleware( [ 'role:student' ] )->group( function () {
		Route::get( '/dashboard/student', [ DashboardController::class, 'studentDashboard' ] );
		Route::get( '/my-messages', [ MessageController::class, 'myMessages' ] );
	} );

	// Debug route to test student role
	Route::get('/debug/role', function() {
		$user = auth()->user();
		if (!$user) {
			return response()->json(['error' => 'No user']);
		}
		$user->load('role');
		return response()->json([
			'user_id' => $user->id,
			'role_id' => $user->role_id,
			'role_name' => $user->role ? $user->role->name : 'No role',
			'has_student_role' => $user->hasRole('student')
		]);
	});

	// Debug student access to messages
	Route::middleware(['role:student'])->get('/debug/student-messages', function() {
		return response()->json(['message' => 'Student can access this']);
	});

	// Message management (admin, sudo, guard, student can access messages)
	Route::middleware( [ 'role:admin,sudo,guard,student' ] )->group( function () {
		Route::get( '/messages', [ MessageController::class, 'index' ] );
		Route::get( '/messages/unread-count', [ MessageController::class, 'unreadCount' ] );
		Route::get( '/messages/{id}', [ MessageController::class, 'show' ] );
		Route::post( '/messages/{id}/read', [ MessageController::class, 'markAsRead' ] );
		Route::put( '/messages/{id}/mark-read', [ MessageController::class, 'markAsRead' ] );
	} );

	// Message creation/management (admin, sudo, guard can create/manage messages)
	Route::middleware( [ 'role:admin,sudo,guard' ] )->group( function () {
		Route::post( '/messages', [ MessageController::class, 'store' ] );
		Route::put( '/messages/{id}', [ MessageController::class, 'update' ] );
		Route::delete( '/messages/{id}', [ MessageController::class, 'destroy' ] );
		Route::post( '/messages/{id}/send', [ MessageController::class, 'send' ] );
	} );

	// Student routes
	Route::middleware( [ 'role:student' ] )->group( function () {
		// Additional student-specific routes can go here
	} );

	// General authenticated user routes (no specific role requirement)
	Route::get( '/users/profile', [ UserController::class, 'profile' ] );
	Route::put( '/users/profile', [ UserController::class, 'updateProfile' ] );
	Route::put( '/users/change-password', [ UserController::class, 'changePassword' ] );

	// Admin and sudo routes
	Route::middleware( [ 'role:admin,sudo' ] )->group( function () {

		// User management
		Route::apiResource( 'users', UserController::class);

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

		// Student management (admins and sudo can manage students)
		Route::get( '/students/export', [ StudentController::class, 'export' ] );
		Route::apiResource( 'students', StudentController::class);
		Route::patch( '/students/{id}/approve', [ StudentController::class, 'approve' ] );

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
		
		// Room management
		Route::apiResource( 'rooms', RoomController::class);

	} );
} );