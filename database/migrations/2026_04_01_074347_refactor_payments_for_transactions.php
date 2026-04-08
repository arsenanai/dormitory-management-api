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
        Schema::table('payments', function (Blueprint $table) {
            // Add paid_amount column
            $table->decimal('paid_amount', 10, 2)->default(0.00)->after('amount');

            // Update status enum - we need to drop and recreate it
            $table->dropColumn('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'partially_paid',  // NEW
                'completed',
                'cancelled',
                'expired',
                // REMOVED: Processing, Failed, Refunded (these are now on Transaction)
            ])->default('pending')->after('paid_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
            $table->dropColumn('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled',
                'refunded',
                'expired',
            ])->default('pending')->after('amount');
        });
    }
};
