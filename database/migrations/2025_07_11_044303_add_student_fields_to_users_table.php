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
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_id')->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->enum('blood_type', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
            $table->string('course')->nullable();
            $table->string('faculty')->nullable();
            $table->string('specialty')->nullable();
            $table->integer('enrollment_year')->nullable();
            $table->integer('graduation_year')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->text('violations')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'student_id', 'birth_date', 'blood_type', 'course', 'faculty',
                'specialty', 'enrollment_year', 'graduation_year', 'gender',
                'emergency_contact', 'emergency_phone', 'violations'
            ]);
        });
    }
};
