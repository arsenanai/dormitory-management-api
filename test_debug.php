<?php

require __DIR__ . '/vendor/autoload.php';

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DebugTest extends TestCase {
    use RefreshDatabase;
    
    public function test_student_debug() {
        // Create role
        $studentRole = Role::create(['name' => 'student']);
        
        // Create student
        $student = User::factory()->create([
            'role_id' => $studentRole->id,
            'email' => 'student@test.com',
        ]);
        
        // Test debug route
        $response = $this->actingAs($student)
            ->getJson('/api/debug/role');
        
        echo "Debug role response: " . $response->getContent() . "\n";
        
        // Test student middleware route
        $response2 = $this->actingAs($student)
            ->getJson('/api/debug/student-messages');
        
        echo "Student middleware response: " . $response2->status() . " - " . $response2->getContent() . "\n";
        
        // Test actual messages route
        $response3 = $this->actingAs($student)
            ->getJson('/api/messages');
        
        echo "Messages route response: " . $response3->status() . " - " . $response3->getContent() . "\n";
    }
}

$test = new DebugTest();
$test->setUp();
$test->test_student_debug();
