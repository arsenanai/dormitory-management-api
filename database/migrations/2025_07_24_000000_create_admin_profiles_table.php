<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void {
		Schema::create( 'admin_profiles', function (Blueprint $table) {
			$table->id();
			$table->foreignId( 'user_id' )->constrained()->onDelete( 'cascade' );
			$table->string( 'position' )->nullable();
			$table->string( 'department' )->nullable();
			$table->string( 'office_phone' )->nullable();
			$table->string( 'office_location' )->nullable();
			$table->timestamps();
		} );
	}

	public function down(): void {
		Schema::dropIfExists( 'admin_profiles' );
	}
};