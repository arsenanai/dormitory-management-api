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
        Schema::table('dormitories', function (Blueprint $table) {
            if (Schema::hasColumn('dormitories', 'quota')) {
                $table->dropColumn('quota');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dormitories', function (Blueprint $table) {
            if (! Schema::hasColumn('dormitories', 'quota')) {
                $table->integer('quota')->nullable()->after('capacity');
            }
        });
    }
};
