<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Turn;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::all();
        foreach ($services as $service) {
            for ($i = 0; $i < 10; $i++) {
                Turn::create([
                    'ticket_number' => $service->code . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'service_id' => $service->id,
                    'status' => 'waiting',
                    'priority' => 'normal',
                    'customer_name' => 'Cliente ' . $i,
                    'totem_id' => 1,
                ]);
            }
        }
    }
}
