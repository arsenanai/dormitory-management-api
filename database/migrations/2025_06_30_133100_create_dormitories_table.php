<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create( 'dormitories', function (Blueprint $table) {
			$table->id();
			$table->string( 'name' );
			$table->string( 'address' )->nullable();
			$table->string( 'description' )->nullable();
			$table->enum( 'gender', [ 'male', 'female', 'mixed' ] )->default( 'mixed' );
			$table->integer( 'capacity' );
			$table->integer( 'quota' )->nullable();
			$table->string( 'phone' )->nullable();
			$table->unsignedBigInteger( 'admin_id' )->nullable();
			$table->json( 'room_ranges' )->nullable();
			$table->timestamps();
			// $table->foreign( 'admin_id' )->references( 'id' )->on( 'users' )->onDelete( 'set null' );
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists( 'dormitories' );
	}
};
