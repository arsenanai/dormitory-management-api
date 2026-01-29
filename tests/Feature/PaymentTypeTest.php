<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentTypeTest extends TestCase
{
    use RefreshDatabase;

    /** @var User */
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Role $adminRole */
        $adminRole = Role::factory()->create([ 'name' => 'admin' ]);

        /** @var User $admin */
        $admin = User::factory()->create([ 'role_id' => $adminRole->id ]);

        $this->admin = $admin;
    }

    #[Test ]
    public function it_can_list_all_payment_types(): void
    {
        PaymentType::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/payment-types');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test ]
    public function it_validates_data_when_creating_a_payment_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/payment-types', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'frequency',
                'calculation_method',
                'target_role'
            ]);
    }

    #[Test ]
    public function it_can_create_a_payment_type(): void
    {
        $data = [
            'name'               => 'Catering Fee',
            'frequency'          => 'monthly',
            'calculation_method' => 'fixed',
            'fixed_amount'       => 50.00,
            'target_role'        => 'student'
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/payment-types', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Catering Fee');

        $this->assertDatabaseHas('payment_types', [
            'name'         => 'Catering Fee',
            'fixed_amount' => 50.00
        ]);
    }

    #[Test ]
    public function it_can_update_a_payment_type(): void
    {
        /** @var PaymentType $type */
        $type = PaymentType::factory()->create([ 'name' => 'Old Name' ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/payment-types/{$type->id}", [
                'name'               => 'New Name',
                'frequency'          => 'once',
                'calculation_method' => 'room_daily_rate',
                'target_role'        => 'guest'
            ]);

        $response->assertOk();
        $this->assertEquals('New Name', $type->refresh()->name);
    }

    #[Test ]
    public function it_can_delete_a_payment_type(): void
    {
        /** @var PaymentType $type */
        $type = PaymentType::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/payment-types/{$type->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('payment_types', [ 'id' => $type->id ]);
    }
}
