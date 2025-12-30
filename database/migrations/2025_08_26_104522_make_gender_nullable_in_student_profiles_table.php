<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // For PostgreSQL, we need to use raw SQL to alter the column
            DB::statement('ALTER TABLE student_profiles ALTER COLUMN gender DROP NOT NULL');
        } else {
            // For SQLite and other databases, use Schema builder
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->enum('gender', [ 'male', 'female', 'other' ])->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // First, update any null values to a default value
            DB::statement("UPDATE student_profiles SET gender = 'male' WHERE gender IS NULL");

            // Then make the column NOT NULL again
            DB::statement('ALTER TABLE student_profiles ALTER COLUMN gender SET NOT NULL');
        } else {
            // For SQLite and other databases, use Schema builder
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->enum('gender', [ 'male', 'female', 'other' ])->nullable(false)->change();
            });
        }
    }
};
