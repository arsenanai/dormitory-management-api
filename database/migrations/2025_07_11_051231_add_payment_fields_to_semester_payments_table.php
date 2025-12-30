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
        Schema::table('semester_payments', function (Blueprint $table) {
            $table->string('contract_number')->nullable();
            $table->date('contract_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semester_payments', function (Blueprint $table) {
            $table->dropColumn(['contract_number', 'contract_date', 'payment_date', 'payment_method']);
        });
    }
};
