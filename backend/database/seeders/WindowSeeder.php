<?php

namespace Database\Seeders;

use App\Models\Window;
use Illuminate\Database\Seeder;

class WindowSeeder extends Seeder
{
    public function run(): void
    {
        $windows = [
            [
                'name' => 'Caja 1',
                'number' => 1,
                'ip_address' => '192.168.0.131',
                'services' => [1], // AC
                'is_active' => true,
            ],
            [
                'name' => 'Caja 2',
                'number' => 2,
                'ip_address' => null,
                'services' => [2], // PD
                'is_active' => true,
            ],
            [
                'name' => 'Caja 3',
                'number' => 3,
                'ip_address' => null,
                'services' => [3], // LB
                'is_active' => true,
            ],
            [
                'name' => 'Caja 4',
                'number' => 4,
                'ip_address' => null,
                'services' => [4], // KN
                'is_active' => true,
            ],
            [
                'name' => 'Caja 5',
                'number' => 5,
                'ip_address' => null,
                'services' => [5], // FA
                'is_active' => true,
            ],
        ];

        foreach ($windows as $window) {
            Window::create($window);
        }
    }
}
