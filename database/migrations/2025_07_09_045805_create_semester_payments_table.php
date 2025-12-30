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
        Schema::create('semester_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('semester'); // e.g., "2025-fall", "2025-spring"
            $table->integer('year');
            $table->enum('semester_type', [ 'fall', 'spring', 'summer' ]);
            $table->decimal('amount', 10, 2);
            $table->boolean('payment_approved')->default(false);
            $table->boolean('dormitory_access_approved')->default(false);
            $table->timestamp('payment_approved_at')->nullable();
            $table->timestamp('dormitory_approved_at')->nullable();
            $table->foreignId('payment_approved_by')->nullable()->constrained('users');
            $table->foreignId('dormitory_approved_by')->nullable()->constrained('users');
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->text('payment_notes')->nullable();
            $table->text('dormitory_notes')->nullable();
            $table->enum('payment_status', [ 'pending', 'approved', 'rejected', 'expired' ])->default('pending');
            $table->enum('dormitory_status', [ 'pending', 'approved', 'rejected', 'expired' ])->default('pending');
            $table->timestamps();
            $table->string('receipt_file')->nullable();
            $table->unique([ 'user_id', 'semester' ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semester_payments');
    }
};
