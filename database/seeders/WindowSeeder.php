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
                'name' => 'Ventanilla de Atención al Cliente',
                'number' => 1,
                'services' => [1], // AC
                'is_active' => true,
            ],
            [
                'name' => 'Ventanilla de Pediatría',
                'number' => 2,
                'services' => [2], // PD
                'is_active' => true,
            ],
            [
                'name' => 'Ventanilla de Laboratorio',
                'number' => 3,
                'services' => [3], // LB
                'is_active' => true,
            ],
            [
                'name' => 'Ventanilla de Kinesiología',
                'number' => 4,
                'services' => [4], // KN
                'is_active' => true,
            ],
            [
                'name' => 'Ventanilla de Fonoaudiología',
                'number' => 5,
                'services' => [5], // FA
                'is_active' => true,
            ],
        ];

        foreach ($windows as $window) {
            Window::create($window);
        }
    }
}
