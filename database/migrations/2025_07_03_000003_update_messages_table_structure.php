<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::table( 'messages', function (Blueprint $table) {
			// Update messages table structure
			$table->dropColumn( [ 'from', 'to', 'subject', 'date_time' ] );

			$table->foreignId( 'sender_id' )->constrained( 'users' )->after( 'id' );
			$table->string( 'title' )->after( 'sender_id' );
			$table->enum( 'recipient_type', [ 'all', 'dormitory', 'room', 'individual' ] )->after( 'content' );
			$table->foreignId( 'dormitory_id' )->nullable()->constrained( 'dormitories' )->after( 'recipient_type' );
			$table->foreignId( 'room_id' )->nullable()->constrained( 'rooms' )->after( 'dormitory_id' );
			$table->json( 'recipient_ids' )->nullable()->after( 'room_id' );
			$table->string( 'status' )->default( 'draft' )->after( 'recipient_ids' );
			$table->datetime( 'sent_at' )->nullable()->after( 'status' );
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::table( 'messages', function (Blueprint $table) {
			$table->dropColumn( [ 
				'sender_id',
				'title',
				'recipient_type',
				'dormitory_id',
				'room_id',
				'recipient_ids',
				'status',
				'sent_at'
			] );

			$table->string( 'from' )->after( 'id' );
			$table->string( 'to' )->after( 'from' );
			$table->string( 'subject' )->after( 'to' );
			$table->datetime( 'date_time' )->after( 'content' );
		} );
	}
};
