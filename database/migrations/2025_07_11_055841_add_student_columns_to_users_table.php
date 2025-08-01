<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add columns if they don't exist
            if (!Schema::hasColumn('users', 'blood_type')) {
                $table->string('blood_type')->nullable();
            }
            if (!Schema::hasColumn('users', 'emergency_contact')) {
                $table->string('emergency_contact')->nullable();
            }
            if (!Schema::hasColumn('users', 'emergency_phone')) {
                $table->string('emergency_phone')->nullable();
            }
            if (!Schema::hasColumn('users', 'course')) {
                $table->string('course')->nullable();
            }
            if (!Schema::hasColumn('users', 'faculty')) {
                $table->string('faculty')->nullable();
            }
            if (!Schema::hasColumn('users', 'specialty')) {
                $table->string('specialty')->nullable();
            }
            if (!Schema::hasColumn('users', 'enrollment_year')) {
                $table->integer('enrollment_year')->nullable();
            }
            if (!Schema::hasColumn('users', 'graduation_year')) {
                $table->integer('graduation_year')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['blood_type', 'emergency_contact', 'emergency_phone', 'course', 'faculty', 'specialty', 'enrollment_year', 'graduation_year'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};