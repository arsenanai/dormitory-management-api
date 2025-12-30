<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['sender_id']);

            // Recreate it with cascade delete
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Drop the cascade foreign key constraint
            $table->dropForeign(['sender_id']);

            // Recreate it without cascade delete (original state)
            $table->foreign('sender_id')->references('id')->on('users');
        });
    }
};
