<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::table( 'dormitories', function (Blueprint $table) {
			$table->string( 'address' )->nullable();
			$table->string( 'description' )->nullable();
			$table->enum( 'gender', [ 'male', 'female', 'mixed' ] )->default( 'mixed' );
			$table->integer( 'quota' )->nullable();
			$table->string( 'phone' )->nullable();
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::table( 'dormitories', function (Blueprint $table) {
			$table->dropColumn( [ 'address', 'description', 'gender', 'quota', 'phone' ] );
		} );
	}
};
