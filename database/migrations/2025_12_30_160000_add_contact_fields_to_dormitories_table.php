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
        Schema::table('dormitories', function (Blueprint $table) {
            $table->string('reception_phone')->nullable()->after('phone');
            $table->string('medical_phone')->nullable()->after('reception_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dormitories', function (Blueprint $table) {
            $table->dropColumn(['reception_phone', 'medical_phone']);
        });
    }
};