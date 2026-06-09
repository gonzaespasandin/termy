<?php

namespace Database\Seeders;

use App\Models\Totem;
use Illuminate\Database\Seeder;

class TotemSeeder extends Seeder
{
    public function run(): void
    {
        $totems = [
            [
                'name' => 'Totem Principal - Recepción',
                'code' => 'TOTEM-PRINCIPAL',
                'location' => 'Planta Baja - Recepción Principal',
                'services' => [1, 2, 3, 4, 5],
                'is_active' => true,
                'ask_customer_dni' => false,
            ],
            [
                'name' => 'Totem Pediatría-Kinesiología',
                'code' => 'TOTEM-PEDIATRIA-KINESIOLOGIA',
                'location' => 'Piso 2 - Área Pediatría - Kinesiología',
                'services' => [2, 4],
                'is_active' => true,
            ],
            [
                'name' => 'Totem Laboratorio',
                'code' => 'TOTEM-LABORATORIO',
                'location' => 'Piso 2 - Área Laboratorio',
                'services' => [3],
                'is_active' => true,
            ],
            [
                'name' => 'Totem Fonoaudiología',
                'code' => 'TOTEM-FONOAUDIOLOGIA',
                'location' => 'Piso 3 - Área Fonoaudiología',
                'services' => [5],
                'is_active' => true,
            ],
        ];

        foreach ($totems as $totem) {
            Totem::create($totem);
        }
    }
}

