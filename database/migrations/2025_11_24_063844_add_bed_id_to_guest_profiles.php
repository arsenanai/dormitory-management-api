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
        if (! Schema::hasColumn('guest_profiles', 'bed_id')) {
            Schema::table('guest_profiles', function (Blueprint $table) {
                $table->unsignedBigInteger('bed_id')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('guest_profiles', 'bed_id')) {
            Schema::table('guest_profiles', function (Blueprint $table) {
                $table->dropColumn('bed_id');
            });
        }
    }
};
