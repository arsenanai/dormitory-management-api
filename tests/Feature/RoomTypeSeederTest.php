<?php

namespace Tests\Feature;

use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoomTypeSeeder::class);
    }

    public function test_only_standard_and_lux_room_types_are_seeded()
    {
        // Get all room types
        $roomTypes = RoomType::all();
        
        // Should have exactly 2 room types
        $this->assertEquals(2, $roomTypes->count(), 'Should have exactly 2 room types');
        
        // Should have only 'standard' and 'lux' room types
        $roomTypeNames = $roomTypes->pluck('name')->toArray();
        sort($roomTypeNames);
        
        $expectedNames = ['lux', 'standard'];
        sort($expectedNames);
        
        $this->assertEquals($expectedNames, $roomTypeNames, 'Should have only standard and lux room types');
    }

    public function test_standard_room_type_has_correct_properties()
    {
        $standardRoomType = RoomType::where('name', 'standard')->first();
        
        $this->assertNotNull($standardRoomType, 'Standard room type should exist');
        $this->assertEquals('standard', $standardRoomType->name);
        $this->assertEquals(2, $standardRoomType->capacity);
        $this->assertNotNull($standardRoomType->beds, 'Standard room type should have beds configuration');
        $this->assertEquals('10000.00', $standardRoomType->daily_rate);
        $this->assertEquals('300000.00', $standardRoomType->semester_rate);
        
        $beds = is_string($standardRoomType->beds) ? json_decode($standardRoomType->beds, true) : $standardRoomType->beds;
        $this->assertIsArray($beds, 'Beds should be an array');
        $this->assertGreaterThan(0, count($beds), 'Should have at least one bed');
    }

    public function test_lux_room_type_has_correct_properties()
    {
        $luxRoomType = RoomType::where('name', 'lux')->first();
        
        $this->assertNotNull($luxRoomType, 'Lux room type should exist');
        $this->assertEquals('lux', $luxRoomType->name);
        $this->assertEquals(1, $luxRoomType->capacity);
        $this->assertNotNull($luxRoomType->beds, 'Lux room type should have beds configuration');
        $this->assertEquals('20000.00', $luxRoomType->daily_rate);
        $this->assertEquals('500000.00', $luxRoomType->semester_rate);
        
        $beds = is_string($luxRoomType->beds) ? json_decode($luxRoomType->beds, true) : $luxRoomType->beds;
        $this->assertIsArray($beds, 'Beds should be an array');
        $this->assertGreaterThan(0, count($beds), 'Should have at least one bed');
    }

    public function test_no_other_room_types_exist()
    {
        $unexpectedRoomTypes = RoomType::whereNotIn('name', ['standard', 'lux'])->get();
        
        $this->assertEquals(0, $unexpectedRoomTypes->count(), 'Should not have any room types other than standard and lux');
    }

    public function test_room_types_have_required_fields()
    {
        $roomTypes = RoomType::all();
        
        foreach ($roomTypes as $roomType) {
            $this->assertNotNull($roomType->name, 'Room type should have a name');
            $this->assertNotNull($roomType->daily_rate, 'Room type should have a daily_rate');
            $this->assertNotNull($roomType->semester_rate, 'Room type should have a semester_rate');
            $this->assertNotNull($roomType->created_at, 'Room type should have created_at timestamp');
            $this->assertNotNull($roomType->updated_at, 'Room type should have updated_at timestamp');
        }
    }
}
