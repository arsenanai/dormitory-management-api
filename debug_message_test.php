<?php

require_once 'bootstrap/app.php';

use App\Models\User;
use App\Models\Role;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

// Set up database for testing
\Illuminate\Support\Facades\Artisan::call('migrate:fresh');

// Create roles
$adminRole = Role::create(['name' => 'admin']);
$studentRole = Role::create(['name' => 'student']);

// Create test users
$admin = User::factory()->create([
    'role_id' => $adminRole->id,
    'email' => 'admin@test.com',
]);

$student = User::factory()->create([
    'role_id' => $studentRole->id,
    'email' => 'student@test.com',
]);

echo "Admin ID: " . $admin->id . "\n";
echo "Student ID: " . $student->id . "\n";

// Create messages
$message1 = Message::factory()->create([
    'sender_id' => $admin->id,
    'title' => 'Message for All',
    'content' => 'This is for everyone',
    'recipient_type' => 'all',
    'status' => 'sent',
]);

$message2 = Message::factory()->create([
    'sender_id' => $admin->id,
    'title' => 'Individual Message',
    'content' => 'This is for specific student',
    'recipient_type' => 'individual',
    'recipient_ids' => json_encode([$student->id]),
    'status' => 'sent',
]);

echo "Message 1 ID: " . $message1->id . " - Type: " . $message1->recipient_type . "\n";
echo "Message 2 ID: " . $message2->id . " - Type: " . $message2->recipient_type . " - Recipient IDs: " . $message2->recipient_ids . "\n";

// Test the query directly
$userId = $student->id;
$query = Message::where('status', 'sent')
    ->where(function ($q) use ($userId) {
        // Messages for all students
        $q->where('recipient_type', 'all')
            // Individual messages - use LIKE for SQLite compatibility
            ->orWhere(function ($subQ) use ($userId) {
                $subQ->where('recipient_type', 'individual')
                    ->where('recipient_ids', 'LIKE', '%"' . $userId . '"%');
            });
    });

echo "\nSQL Query: " . $query->toSql() . "\n";
echo "Query Bindings: " . json_encode($query->getBindings()) . "\n";

$messages = $query->get();
echo "Found " . $messages->count() . " messages\n";

foreach ($messages as $msg) {
    echo "- Message ID: " . $msg->id . " - Type: " . $msg->recipient_type . " - Recipient IDs: " . $msg->recipient_ids . "\n";
}
