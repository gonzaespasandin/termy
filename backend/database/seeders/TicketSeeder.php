<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Window;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Turn;
use Carbon\Carbon;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::all();
        $windows  = Window::all()->keyBy('id');

        // Cycling states: 8 turns per service
        $cycle = [
            'waiting',
            'waiting',
            'called',
            'in_progress',
            'completed',
            'cancelled',
            'no_show',
            'waiting',
        ];

        $now = Carbon::now();

        foreach ($services as $service) {
            // Use the window whose services JSON includes this service's id
            $window = $windows->first(fn ($w) => in_array($service->id, $w->services ?? []))
                     ?? $windows->first();

            foreach ($cycle as $idx => $status) {
                $number = str_pad($idx + 1, 3, '0', STR_PAD_LEFT);

                $data = [
                    'ticket_number' => "{$service->code}-{$number}",
                    'service_id'    => $service->id,
                    'status'        => $status,
                    'priority'      => 'normal',
                    'totem_id'      => 1,
                    'issued_at'     => $now->copy()->subMinutes((count($cycle) - $idx) * 5),
                    'created_at'    => $now->copy()->subMinutes((count($cycle) - $idx) * 5),
                    'updated_at'    => $now->copy()->subMinutes((count($cycle) - $idx) * 3),
                ];

                if (in_array($status, ['called', 'in_progress', 'completed', 'cancelled', 'no_show'])) {
                    $data['window_id'] = $window?->id;
                    $data['called_at'] = $now->copy()->subMinutes((count($cycle) - $idx) * 3);
                }

                if (in_array($status, ['completed', 'cancelled', 'no_show'])) {
                    $data['completed_at'] = $now->copy()->subMinutes(count($cycle) - $idx);
                }

                Turn::create($data);
            }
        }
    }
}
