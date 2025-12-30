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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users');
            $table->string('title');
            $table->text('content');
            $table->enum('recipient_type', [ 'all', 'dormitory', 'room', 'individual' ]);
            $table->foreignId('dormitory_id')->nullable()->constrained('dormitories');
            $table->foreignId('room_id')->nullable()->constrained('rooms');
            $table->json('recipient_ids')->nullable();
            $table->string('status')->default('draft');
            $table->datetime('sent_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
