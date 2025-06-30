<?php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DormitoryController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\UserController;

Route::post( '/login', [ UserController::class, 'login' ] );

// Sudo-only routes for managing dormitories and admins
Route::middleware( [ 'auth:sanctum', 'role:sudo' ] )->group( function () {
	// CRUD Admins (users with admin role)
	Route::apiResource( 'admins', AdminController::class);

	// CRUD Dormitories
	Route::apiResource( 'dormitories', DormitoryController::class);

	// Assign an admin to a dormitory
	Route::post( 'dormitories/{dormitory}/assign-admin', [ DormitoryController::class, 'assignAdmin' ] );

	Route::apiResource( 'room-types', RoomTypeController::class);

	// CRUD Rooms
	Route::apiResource( 'rooms', RoomController::class );

} );