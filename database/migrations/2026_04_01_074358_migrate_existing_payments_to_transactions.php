<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing payments with status = 'completed'
        $completedPayments = DB::table('payments')->where('status', 'completed')->get();

        foreach ($completedPayments as $payment) {
            $transactionId = DB::table('transactions')->insertGetId([
                'user_id' => $payment->user_id,
                'amount' => $payment->amount,
                'payment_method' => 'bank_check',
                'payment_check' => $payment->payment_check,
                'status' => 'completed',
                'created_at' => $payment->updated_at, // approximation of when it was paid
                'updated_at' => $payment->updated_at,
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
            DB::table('payments')->where('id', $payment->id)->update([
                'paid_amount' => $payment->amount,
            ]);
        }

        // Migrate existing payments with status = 'processing'
        $processingPayments = DB::table('payments')->where('status', 'processing')->get();

        foreach ($processingPayments as $payment) {
            $transactionId = DB::table('transactions')->insertGetId([
                'user_id' => $payment->user_id,
                'amount' => $payment->amount,
                'payment_method' => 'bank_check',
                'payment_check' => $payment->payment_check,
                'status' => 'processing',
                'created_at' => $payment->updated_at,
                'updated_at' => $payment->updated_at,
            ]);

            // Create pivot record
            DB::table('payment_transaction')->insert([
                'payment_id' => $payment->id,
                'transaction_id' => $transactionId,
                'amount' => $payment->amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Keep payment.status = 'pending' (not yet confirmed)
            // Set payment.paid_amount = 0 (not confirmed yet)
            DB::table('payments')->where('id', $payment->id)->update([
                'status' => 'pending',
                'paid_amount' => 0,
            ]);
        }

        // For existing payments with status = 'pending' (no check uploaded)
        // No transaction created, paid_amount remains 0
        DB::table('payments')->where('status', 'pending')->update([
            'paid_amount' => 0,
        ]);

        // Drop the payment_check column from payments after migration
        Schema::table('payments', function ($table) {
            $table->dropColumn('payment_check');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back payment_check column
        Schema::table('payments', function ($table) {
            $table->string('payment_check')->nullable()->after('deal_date');
        });

        // Reverse the data migration
        // Get all transactions and move payment_check back to payments
        $transactions = DB::table('transactions')->get();

        foreach ($transactions as $transaction) {
            // Get the associated payment
            $paymentTransaction = DB::table('payment_transaction')
                ->where('transaction_id', $transaction->id)
                ->first();

            if ($paymentTransaction) {
                // Update payment with transaction data
                DB::table('payments')->where('id', $paymentTransaction->payment_id)->update([
                    'payment_check' => $transaction->payment_check,
                    'status' => $transaction->status,
                    'paid_amount' => $transaction->status === 'completed' ? $transaction->amount : 0,
                ]);
            }
        }

        // Clean up
        DB::table('payment_transaction')->truncate();
        DB::table('transactions')->truncate();
    }
};
