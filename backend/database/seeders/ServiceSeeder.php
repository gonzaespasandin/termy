<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Atención al Cliente',
                'code' => 'AC',
                'color' => '#3B82F6', // Azul
                'is_active' => true,
                'description' => 'Consultas generales y atención al cliente',
            ],
            [
                'name' => 'Pediatría',
                'code' => 'PD',
                'color' => '#10B981', // Verde
                'is_active' => true,
                'description' => 'Atención pediátrica',
            ],
            [
                'name' => 'Laboratorio',
                'code' => 'LB',
                'color' => '#F59E0B', // Amarillo/Naranja
                'is_active' => true,
                'description' => 'Análisis de laboratorio',
            ],
            [
                'name' => 'Kinesiología',
                'code' => 'KN',
                'color' => '#EF4444', // Rojo
                'is_active' => true,
                'description' => 'Atención kinesiología',
            ],
            [
                'name' => 'Fonoaudiología',
                'code' => 'FA',
                'color' => '#8B5CF6', // Púrpura
                'is_active' => true,
                'description' => 'Atención fonoaudiología',
            ],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }
    }
}
