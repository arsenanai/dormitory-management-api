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
            // Drop all foreign keys before dropping columns or unique indexes.
            // Laravel's convention for foreign key names is `table_column_foreign`.
            $table->dropForeign([ 'user_id' ]);
            $table->dropForeign([ 'payment_approved_by' ]);
            $table->dropForeign([ 'dormitory_approved_by' ]);
            $table->dropUnique('semester_payments_user_id_semester_unique');

            // Drop columns that are no longer needed
            $table->dropColumn([
                'semester',
                'year',
                'semester_type',
                'payment_approved',
                'dormitory_access_approved',
                'payment_approved_at',
                'dormitory_approved_at',
                'payment_approved_by',
                'dormitory_approved_by',
                'due_date',
                'paid_date',
                'payment_notes',
                'dormitory_notes',
                'payment_status',
                'dormitory_status',
                'payment_method',
                'payment_date',
            ]);

            // Rename columns to match new model
            $table->renameColumn('contract_number', 'deal_number');
            $table->renameColumn('contract_date', 'deal_date');
            $table->renameColumn('receipt_file', 'payment_check');

            // Add new date columns
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();

            // Re-add the foreign key for user_id to the new 'payments' table
            $table->foreign('user_id', 'payments_user_id_foreign')->references('id')->on('users')->onDelete('cascade');
        });

        // Rename the table
        Schema::rename('semester_payments', 'payments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename the table back first
        Schema::rename('payments', 'semester_payments');

        Schema::table('semester_payments', function (Blueprint $table) {
            // In case the 'up' migration failed after renaming, check for columns before dropping.
            if (Schema::hasColumn('semester_payments', 'date_from')) {
                $table->dropColumn([ 'date_from', 'date_to' ]);
            }

            // Drop the foreign key we added in the 'up' method before proceeding
            $table->dropForeign('payments_user_id_foreign');

            // Drop the new columns
            if (Schema::hasColumn('semester_payments', 'date_from')) {
                $table->dropColumn([ 'date_from', 'date_to' ]);
            }

            // Rename columns back to their original names
            if (Schema::hasColumn('semester_payments', 'deal_number') && !Schema::hasColumn('semester_payments', 'contract_number')) {
                $table->renameColumn('deal_number', 'contract_number');
            }
            if (Schema::hasColumn('semester_payments', 'deal_date') && !Schema::hasColumn('semester_payments', 'contract_date')) {
                $table->renameColumn('deal_date', 'contract_date');
            }
            if (Schema::hasColumn('semester_payments', 'payment_check') && !Schema::hasColumn('semester_payments', 'receipt_file')) {
                $table->renameColumn('payment_check', 'receipt_file');
            }

            // Re-add the dropped columns
            if (!Schema::hasColumn('semester_payments', 'semester')) {
                $table->string('semester'); // e.g., "2025-fall", "2025-spring"
                $table->integer('year');
                $table->enum('semester_type', [ 'fall', 'spring', 'summer' ]);
                $table->boolean('payment_approved')->default(false);
                $table->boolean('dormitory_access_approved')->default(false);
                $table->timestamp('payment_approved_at')->nullable();
                $table->timestamp('dormitory_approved_at')->nullable();
                $table->foreignId('payment_approved_by')->nullable()->constrained('users');
                $table->foreignId('dormitory_approved_by')->nullable()->constrained('users');
                $table->date('due_date');
                $table->date('paid_date')->nullable();
            }

            $table->text('payment_notes')->nullable();
            $table->text('dormitory_notes')->nullable();
            $table->enum('payment_status', [ 'pending', 'approved', 'rejected', 'expired' ])->default('pending');
            $table->enum('dormitory_status', [ 'pending', 'approved', 'rejected', 'expired' ])->default('pending');
            $table->string('payment_method')->nullable();
            $table->date('payment_date')->nullable();

            // Re-add the original foreign key for user_id
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Re-add the unique constraint if it doesn't exist
            $table->unique([ 'user_id', 'semester' ], 'semester_payments_user_id_semester_unique');
        });
    }
};
