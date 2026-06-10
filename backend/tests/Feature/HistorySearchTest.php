<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorySearchTest extends TestCase
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

    private function makeTurn(Service $service, string $ticketNumber, ?string $dni): Turn
    {
        return Turn::create([
            'service_id' => $service->id,
            'ticket_number' => $ticketNumber,
            'customer_dni' => $dni,
            'status' => 'completed',
            'priority' => 'normal',
            'issued_at' => now(),
        ]);
    }

    public function test_search_finds_turn_by_dni(): void
    {
        $service = $this->makeService();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->makeTurn($service, 'AC-001', '44770488');
        $this->makeTurn($service, 'AC-002', '11222333');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/history?search=44770488');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.ticket_number', 'AC-001');
    }

    public function test_search_still_finds_turn_by_ticket_number(): void
    {
        $service = $this->makeService();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->makeTurn($service, 'AC-001', '44770488');
        $this->makeTurn($service, 'AC-002', '11222333');

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/history?search=AC-002');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.customer_dni', '11222333');
    }
}
