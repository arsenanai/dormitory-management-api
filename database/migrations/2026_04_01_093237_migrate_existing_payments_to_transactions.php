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
        // Migrate existing payments to transactions
        $this->migrateCompletedPayments();
        $this->migrateProcessingPayments();
        $this->migratePendingPayments();

        // Drop the payment_check column from payments after migration (if it exists)
        if (Schema::hasColumn('payments', 'payment_check')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('payment_check');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back payment_check column
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_check')->nullable();
        });

        // Note: We don't reverse the data migration as it would be complex
        // and could cause data integrity issues
    }

    /**
     * Migrate completed payments to transactions
     */
    private function migrateCompletedPayments(): void
    {
        DB::transaction(function () {
            $completedPayments = DB::table('payments')
                ->where('status', 'completed')
                ->get();

            foreach ($completedPayments as $payment) {
                // Create transaction record
                $transactionId = DB::table('transactions')->insertGetId([
                    'user_id' => $payment->user_id,
                    'amount' => $payment->amount,
                    'payment_method' => 'bank_check',
                    'payment_check' => $payment->payment_check,
                    'status' => 'completed',
                    'created_at' => $payment->updated_at,
                    'updated_at' => now(),
                ]);

                // Create pivot record
                DB::table('payment_transaction')->insert([
                    'payment_id' => $payment->id,
                    'transaction_id' => $transactionId,
                    'amount' => $payment->amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update payment
                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update(['paid_amount' => $payment->amount]);
            }
        });
    }

    /**
     * Migrate processing payments to transactions
     */
    private function migrateProcessingPayments(): void
    {
        DB::transaction(function () {
            $processingPayments = DB::table('payments')
                ->where('status', 'processing')
                ->get();

            foreach ($processingPayments as $payment) {
                // Create transaction record
                $transactionId = DB::table('transactions')->insertGetId([
                    'user_id' => $payment->user_id,
                    'amount' => $payment->amount,
                    'payment_method' => 'bank_check',
                    'payment_check' => $payment->payment_check,
                    'status' => 'processing',
                    'created_at' => $payment->updated_at,
                    'updated_at' => now(),
                ]);

                // Create pivot record
                DB::table('payment_transaction')->insert([
                    'payment_id' => $payment->id,
                    'transaction_id' => $transactionId,
                    'amount' => $payment->amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update payment (keep status as pending, set paid_amount to 0)
                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update([
                        'paid_amount' => 0,
                        'status' => 'pending' // not yet confirmed
                    ]);
            }
        });
    }

    /**
     * Handle pending payments (no transaction created)
     */
    private function migratePendingPayments(): void
    {
        DB::table('payments')
            ->where('status', 'pending')
            ->update(['paid_amount' => 0]);
    }
};
