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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('iin')->unique();
            $table->string('student_id')->unique();
            $table->string('faculty')->nullable();
            $table->string('specialist')->nullable();
            $table->integer('course')->nullable();
            $table->integer('year_of_study')->nullable();
            $table->string('enrollment_year')->nullable();
            $table->date('enrollment_date')->nullable();
            $table->string('blood_type')->nullable();
            $table->text('violations')->nullable();
            $table->string('parent_name')->nullable();
            $table->string('parent_phone')->nullable();
            $table->string('parent_email')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('mentor_name')->nullable();
            $table->string('mentor_email')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('medical_conditions')->nullable();
            $table->string('dietary_restrictions')->nullable();
            $table->string('program')->nullable();
            $table->integer('year_level')->nullable();
            $table->string('nationality')->nullable();
            $table->string('deal_number')->nullable();
            $table->boolean('agree_to_dormitory_rules')->default(false);
            $table->boolean('has_meal_plan')->default(false);
            $table->boolean('registration_limit_reached')->default(false);
            $table->boolean('is_backup_list')->default(false);
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', [ 'male', 'female', 'other' ]);
            $table->json('files')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
