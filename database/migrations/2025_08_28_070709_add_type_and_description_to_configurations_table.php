<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::table( 'configurations', function (Blueprint $table) {
			$table->string( 'type' )->default( 'string' )->after( 'value' );
			$table->text( 'description' )->nullable()->after( 'type' );
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::table( 'configurations', function (Blueprint $table) {
			$table->dropColumn( [ 'type', 'description' ] );
		} );
	}
};
