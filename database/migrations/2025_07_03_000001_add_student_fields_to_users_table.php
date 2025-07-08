<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::table( 'users', function (Blueprint $table) {
			// Add missing fields for student management
			$table->string( 'blood_type' )->nullable()->after( 'gender' );
			$table->text( 'violations' )->nullable()->after( 'blood_type' );
			$table->string( 'parent_name' )->nullable()->after( 'violations' );
			$table->string( 'parent_phone' )->nullable()->after( 'parent_name' );
			$table->string( 'mentor_name' )->nullable()->after( 'parent_phone' );
			$table->string( 'mentor_email' )->nullable()->after( 'mentor_name' );
			$table->boolean( 'has_meal_plan' )->default( false )->after( 'status' );

			// Add additional fields from CSV requirements
			$table->string( 'first_name' )->nullable()->after( 'name' );
			$table->string( 'last_name' )->nullable()->after( 'first_name' );
			$table->string( 'student_id' )->nullable()->unique()->after( 'iin' );
			$table->date( 'date_of_birth' )->nullable()->after( 'last_name' );
			$table->string( 'phone' )->nullable()->after( 'email' );
			$table->string( 'emergency_contact' )->nullable()->after( 'phone' );
			$table->string( 'course' )->nullable()->after( 'specialist' );
			$table->integer( 'year_of_study' )->nullable()->after( 'course' );
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::table( 'users', function (Blueprint $table) {
			$table->dropColumn( [ 
				'blood_type',
				'violations',
				'parent_name',
				'parent_phone',
				'mentor_name',
				'mentor_email',
				'has_meal_plan',
				'first_name',
				'last_name',
				'student_id',
				'date_of_birth',
				'phone',
				'emergency_contact',
				'course',
				'year_of_study',
			] );
		} );
	}
};
