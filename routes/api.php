<?php
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BedController;
use App\Http\Controllers\ConfigurationController;
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
use App\Http\Controllers\Api\BloodTypeController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\CityController;

// Public routes
Route::post( '/login', [ UserController::class, 'login' ] );
Route::get( '/app-version', function () {
	return response()->json( [ 'version' => '1.0.2' ] );
} );
Route::post( '/register', [ UserController::class, 'register' ] );
Route::post( '/password/reset-link', [ UserController::class, 'sendPasswordResetLink' ] );
Route::post( '/password/reset', [ UserController::class, 'resetPassword' ] );
Route::get( '/rooms/available', [ \App\Http\Controllers\RoomController::class, 'available' ] );
Route::get( '/dormitories', [ DormitoryController::class, 'index' ] );
Route::get( '/dormitories/public', [ DormitoryController::class, 'getAllForPublic' ] );
Route::get( '/dormitories/{dormitory}/rooms', [ DormitoryController::class, 'rooms' ] );
Route::get( '/dormitories/{dormitory}/registration', [ DormitoryController::class, 'getForRegistration' ] );
Route::get( '/blood-types', [ BloodTypeController::class, 'index' ] );
// Public room type access (for forms)
Route::get( '/room-types', [ RoomTypeController::class, 'index' ] );
Route::get( '/room-types/{roomType}', [ RoomTypeController::class, 'show' ] );

// Debug route to test API functionality
Route::post( '/debug/test', function () {
	return response()->json( [ 'message' => 'Debug route working', 'timestamp' => now() ] );
} );

// Debug route to test room relationships
Route::get( '/debug/room/{id}', function ($id) {
	$room = \App\Models\Room::find( $id );
	if ( ! $room ) {
		return response()->json( [ 'error' => 'Room not found' ], 404 );
	}

	$roomType = \App\Models\RoomType::find( $room->room_type_id );

	return response()->json( [ 
		'room_id'                    => $room->id,
		'room_number'                => $room->number,
		'room_type_id'               => $room->room_type_id,
		'room_type_direct'           => $roomType,
		'room_type_via_relationship' => $room->roomType,
		'room_with_relationship'     => $room->load( 'roomType' ),
	] );
} );

// Protected routes
Route::middleware( [ 'auth:sanctum' ] )->group( function () {

	// Dashboard routes - role-specific access
	Route::middleware( [ 'role:admin,sudo' ] )->group( function () {
		Route::get( '/dashboard', [ DashboardController::class, 'index' ] );
		Route::get( '/dashboard/stats', [ DashboardController::class, 'index' ] );
		Route::get( '/dashboard/dormitory/{dormitoryId}', [ DashboardController::class, 'dormitoryStats' ] );
		Route::get( '/dashboard/monthly-stats', [ DashboardController::class, 'monthlyStats' ] );
		Route::get( '/dashboard/payment-analytics', [ DashboardController::class, 'paymentAnalytics' ] );

		// Debug route to test role middleware
		Route::post( '/debug/role-test', function () {
			return response()->json( [ 'message' => 'Role middleware working', 'user' => auth()->user()->id, 'role' => auth()->user()->role->name ] );
		} );
	} );

	// Room management (admin, sudo can access rooms)
	Route::middleware( [ 'role:admin,sudo' ] )->group( function () {
		Route::get( '/rooms', [ RoomController::class, 'index' ] );
		Route::get( '/rooms/{room}', [ RoomController::class, 'show' ] );
		Route::post( '/rooms', [ RoomController::class, 'store' ] );
		Route::put( '/rooms/{room}', [ RoomController::class, 'update' ] );
		Route::delete( '/rooms/{room}', [ RoomController::class, 'destroy' ] );
		Route::get( '/rooms/available', [ RoomController::class, 'available' ] );

		// Bed management
		Route::put( '/beds/{bed}', [ BedController::class, 'update' ] );
	} );

	Route::middleware( [ 'role:guard' ] )->group( function () {
		Route::get( '/dashboard/guard', [ DashboardController::class, 'guardDashboard' ] );
	} );

	Route::middleware( [ 'role:student' ] )->group( function () {
		Route::get( '/dashboard/student', [ DashboardController::class, 'studentDashboard' ] );
		Route::get( '/my-messages', [ MessageController::class, 'myMessages' ] );
	} );

	Route::middleware( [ 'role:guest' ] )->group( function () {
		Route::get( '/dashboard/guest', [ DashboardController::class, 'guestDashboard' ] );
	} );

	// Debug route to test student role
	Route::get( '/debug/role', function () {
		$user = auth()->user();
		if ( ! $user ) {
			return response()->json( [ 'error' => 'No user' ] );
		}
		$user->load( 'role' );
		return response()->json( [ 
			'user_id'          => $user->id,
			'role_id'          => $user->role_id,
			'role_name'        => $user->role ? $user->role->name : 'No role',
			'has_student_role' => $user->hasRole( 'student' )
		] );
	} );

	// Debug student access to messages
	Route::middleware( [ 'role:student' ] )->get( '/debug/student-messages', function () {
		return response()->json( [ 'message' => 'Student can access this' ] );
	} );

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
	Route::post( '/logout', [ UserController::class, 'logout' ] );

	// Admin and sudo routes
	Route::middleware( [ 'role:admin,sudo' ] )->group( function () {

		// User management
		Route::apiResource( 'users', UserController::class);

		// Protected dormitory access (with role-based filtering)
		Route::get( '/dormitories/authenticated', [ DormitoryController::class, 'index' ] );

		// Dormitory quota management (admin only)
		Route::get( '/dormitories/{dormitory}/quota', [ DormitoryController::class, 'getQuotaInfo' ] );
		Route::put( '/dormitories/{dormitory}/rooms/{room}/quota', [ DormitoryController::class, 'updateRoomQuota' ] );

		// Accounting routes
		Route::get( '/accounting', [ AccountingController::class, 'index' ] );
		Route::get( '/accounting/student/{studentId}', [ AccountingController::class, 'studentAccounting' ] );
		Route::get( '/accounting/semester/{semester}', [ AccountingController::class, 'semesterAccounting' ] );
		Route::get( '/accounting/export', [ AccountingController::class, 'export' ] );
		Route::get( '/accounting/stats', [ AccountingController::class, 'stats' ] );

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

		// Room management (admins and sudo can manage rooms)
		Route::post( '/rooms', [ RoomController::class, 'store' ] );
		Route::put( '/rooms/{room}', [ RoomController::class, 'update' ] );
		Route::delete( '/rooms/{room}', [ RoomController::class, 'destroy' ] );

		// Region and city management (admins and sudo can manage)
		Route::apiResource( 'regions', RegionController::class);
		Route::apiResource( 'cities', CityController::class);

		// Configuration settings accessible to admin and sudo
		Route::get( '/configurations/card-reader', [ ConfigurationController::class, 'getCardReaderSettings' ] );
		Route::put( '/configurations/card-reader', [ ConfigurationController::class, 'updateCardReaderSettings' ] );
		Route::get( '/configurations/onec', [ ConfigurationController::class, 'getOneCSettings' ] );
		Route::put( '/configurations/onec', [ ConfigurationController::class, 'updateOneCSettings' ] );
		// Kaspi integration settings (admin and sudo can manage)
		Route::get( '/configurations/kaspi', [ ConfigurationController::class, 'getKaspiSettings' ] );
        Route::put('/configurations/kaspi', [ConfigurationController::class, 'updateKaspiSettings']);
        Route::get('/configurations/currency', [ConfigurationController::class, 'getCurrencySetting']);
        Route::put('/configurations/currency', [ConfigurationController::class, 'updateCurrencySetting']);

	} );
	// Room type management (sudo and admin)
	Route::middleware(['role:admin,sudo'])->group(function () {
		Route::post('/room-types', [RoomTypeController::class, 'store']);
		Route::put('/room-types/{roomType}', [RoomTypeController::class, 'update']);
		Route::delete('/room-types/{roomType}', [RoomTypeController::class, 'destroy']);
	});

	// Sudo-only routes
	Route::middleware( [ 'role:sudo' ] )->group( function () {

		// Admin management
		Route::apiResource( 'admins', AdminController::class);

		// Dormitory management (admin operations only) - excluding index which is public
		Route::get( '/dormitories/{dormitory}', [ DormitoryController::class, 'show' ] );
		Route::post( 'dormitories/{dormitory}/assign-admin', [ DormitoryController::class, 'assignAdmin' ] );
		Route::post( '/dormitories', [ DormitoryController::class, 'store' ] );
		Route::put( '/dormitories/{dormitory}', [ DormitoryController::class, 'update' ] );
		Route::delete( '/dormitories/{dormitory}', [ DormitoryController::class, 'destroy' ] );

		// Configuration management
		Route::get( '/configurations', [ ConfigurationController::class, 'index' ] );
		Route::put( '/configurations', [ ConfigurationController::class, 'update' ] );
		Route::post( '/configurations/initialize', [ ConfigurationController::class, 'initializeDefaults' ] );

		// SMTP settings
		Route::get( '/configurations/smtp', [ ConfigurationController::class, 'getSmtpSettings' ] );
		Route::put( '/configurations/smtp', [ ConfigurationController::class, 'updateSmtpSettings' ] );

		// (moved to admin+sudo group above)

		// Language file management
		Route::get( '/configurations/languages', [ ConfigurationController::class, 'getInstalledLanguages' ] );
		Route::post( '/configurations/languages/upload', [ ConfigurationController::class, 'uploadLanguageFile' ] );

		// System logs
		Route::get( '/configurations/logs', [ ConfigurationController::class, 'getSystemLogs' ] );
		Route::delete( '/configurations/logs', [ ConfigurationController::class, 'clearSystemLogs' ] );

		// Dormitory settings
		Route::get( '/configurations/dormitory', [ ConfigurationController::class, 'getDormitorySettings' ] );
		Route::put( '/configurations/dormitory', [ ConfigurationController::class, 'updateDormitorySettings' ] );

	} );

	// Dormitory access check
	Route::middleware( [ 'auth:sanctum' ] )->group( function () {
		Route::get( '/me/can-access-dormitory', [ UserController::class, 'canAccessDormitory' ] );
		Route::get( '/users/{id}/can-access-dormitory', [ UserController::class, 'canAccessDormitory' ] );
	} );

	Route::middleware( 'auth:sanctum' )->get( '/dormitory-access/check', [ \App\Http\Controllers\DormitoryAccessController::class, 'check' ] );
} );