<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Administrador - puede ver todo
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'services' => null, // Admin ve todos los servicios
            'window_id' => null,
        ]);

        // Operador 1 - Ventanilla 1 (Atención al Cliente)
        User::create([
            'name' => 'María González',
            'email' => 'operador1@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'services' => [1], // AC
            'window_id' => 1,
        ]);

        // Operador 2 - Ventanilla 2 (Pediatría)
        User::create([
            'name' => 'Juan Pérez',
            'email' => 'operador2@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'services' => [2], // PD
            'window_id' => 2,
        ]);

        // Operador 3 - Ventanilla 3 (Laboratorio)
        User::create([
            'name' => 'Ana Martínez',
            'email' => 'operador3@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'services' => [3], // LB
            'window_id' => 3,
        ]);

        // Operador 4 - Ventanilla 4 (Kinesiología)
        User::create([
            'name' => 'Carlos Rodríguez',
            'email' => 'operador4@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'services' => [4], // KN
            'window_id' => 4,
        ]);

        // Operador 5 - Ventanilla 5 (Fonoaudiología)
        User::create([
            'name' => 'Pedro González',
            'email' => 'operador5@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'services' => [5], // FA
            'window_id' => 5,
        ]);
    }
}
