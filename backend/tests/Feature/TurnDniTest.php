<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnDniTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): Service
    {
        return Service::create([
            'name' => 'Atencion Cliente',
            'code' => 'AC',
            'color' => '#3b82f6',
            'is_active' => true,
        ]);
    }

    public function test_creates_turn_with_valid_8_digit_dni(): void
    {
        $service = $this->makeService();

        $response = $this->postJson('/api/turns', [
            'service_id' => $service->id,
            'customer_dni' => '44770488',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('customer_dni', '44770488');
        $this->assertDatabaseHas('turns', ['customer_dni' => '44770488']);
    }

    public function test_creates_turn_with_no_dni(): void
    {
        $service = $this->makeService();

        $response = $this->postJson('/api/turns', [
            'service_id' => $service->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('customer_dni', null);
    }

    public function test_rejects_dni_with_wrong_digit_count(): void
    {
        $service = $this->makeService();

        $response = $this->postJson('/api/turns', [
            'service_id' => $service->id,
            'customer_dni' => '123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('customer_dni');
    }

    public function test_rejects_non_numeric_dni(): void
    {
        $service = $this->makeService();

        $response = $this->postJson('/api/turns', [
            'service_id' => $service->id,
            'customer_dni' => 'ABCDEFGH',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('customer_dni');
    }
}
