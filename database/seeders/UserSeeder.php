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
        ]);

        // Operador 1 - Atención al Cliente
        User::create([
            'name' => 'María González',
            'email' => 'operador1@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'services' => [1], // AC
        ]);

        // Operador 2 - Pediatría
        User::create([
            'name' => 'Juan Pérez',
            'email' => 'operador2@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'services' => [2], // PD
        ]);

        // Operador 3 - Laboratorio
        User::create([
            'name' => 'Ana Martínez',
            'email' => 'operador3@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'services' => [3], // LB
        ]);

        // Operador 4 - Kinesiología
        User::create([
            'name' => 'Carlos Rodríguez',
            'email' => 'operador4@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'services' => [4], // KN
        ]);

        // Operador 5 - Fonoaudiología
        User::create([
            'name' => 'Pedro González',
            'email' => 'operador5@turnero.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'services' => [5], // FA
        ]);
    }
}
