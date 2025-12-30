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
        if (! Schema::hasColumn('rooms', 'occupant_type')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->enum('occupant_type', [ 'student', 'guest' ])->nullable()->default('student');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('rooms', 'occupant_type')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('occupant_type');
            });
        }
    }
};
