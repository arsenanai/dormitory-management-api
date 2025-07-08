<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::table( 'payments', function (Blueprint $table) {
			// Add missing fields for payment management
			$table->foreignId( 'user_id' )->nullable()->constrained( 'users' )->after( 'id' );
			$table->datetime( 'payment_date' )->nullable()->after( 'contract_date' );
			$table->string( 'payment_method' )->nullable()->after( 'payment_date' );
			$table->string( 'receipt_file' )->nullable()->after( 'payment_method' );
			$table->string( 'status' )->default( 'pending' )->after( 'receipt_file' );
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::table( 'payments', function (Blueprint $table) {
			$table->dropColumn( [ 
				'user_id',
				'payment_date',
				'payment_method',
				'receipt_file',
				'status'
			] );
		} );
	}
};
