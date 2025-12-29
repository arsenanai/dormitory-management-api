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
        Schema::table('student_profiles', function (Blueprint $table) {
            // Remove old contact fields
            $table->dropColumn([
                'parent_name',
                'parent_phone',
                'parent_email',
                'guardian_name',
                'guardian_phone',
                'mentor_name',
                'mentor_email',
            ]);

            // Add new emergency contact fields
            $table->enum('emergency_contact_type', ['parent', 'guardian', 'other'])->nullable()->after('emergency_contact_phone');
            $table->string('emergency_contact_email')->nullable()->after('emergency_contact_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            // Remove new fields
            $table->dropColumn([
                'emergency_contact_type',
                'emergency_contact_email',
            ]);

            // Restore old contact fields
            $table->string('parent_name')->nullable();
            $table->string('parent_phone')->nullable();
            $table->string('parent_email')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('mentor_name')->nullable();
            $table->string('mentor_email')->nullable();
        });
    }
};
