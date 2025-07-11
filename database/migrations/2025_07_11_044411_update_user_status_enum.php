<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // For SQLite, we need to update the status enum values
            $table->string('status_temp')->default('pending');
        });
        
        // Copy existing data
        DB::statement("UPDATE users SET status_temp = CASE 
            WHEN status = 'active' THEN 'approved' 
            WHEN status = 'passive' THEN 'rejected' 
            ELSE status 
        END");
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('status_temp', 'status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Reverse the changes
            $table->string('status_temp')->default('pending');
        });
        
        DB::statement("UPDATE users SET status_temp = CASE 
            WHEN status = 'approved' THEN 'active' 
            WHEN status = 'rejected' THEN 'passive' 
            ELSE status 
        END");
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('status_temp', 'status');
        });
    }
};
