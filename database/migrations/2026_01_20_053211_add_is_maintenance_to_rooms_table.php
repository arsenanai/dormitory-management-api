<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::table( 'rooms', function ( Blueprint $table ) {
			$table->boolean( 'is_maintenance' )->default( false )->after( 'is_occupied' );
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::table( 'rooms', function ( Blueprint $table ) {
			$table->dropColumn( 'is_maintenance' );
		} );
	}
};
