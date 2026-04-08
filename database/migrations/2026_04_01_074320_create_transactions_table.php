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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);               // actual amount paid
            $table->string('payment_method')->default('bank_check'); // bank_check, kaspi, stripe, etc.
            $table->string('payment_check')->nullable();     // file path to uploaded bank check image
            $table->string('gateway_transaction_id')->nullable(); // external gateway reference ID
            $table->json('gateway_response')->nullable();    // raw response from payment gateway
            $table->string('status')->default('pending');    // pending, processing, completed, failed, cancelled, refunded
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
