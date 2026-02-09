<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
        $this->call([
            ServiceSeeder::class,    // Primero los servicios
            TotemSeeder::class,      // Luego los totems (dependen de servicios)
            WindowSeeder::class,     // Ventanillas (dependen de servicios)
            UserSeeder::class,       // Usuarios (dependen de ventanillas)
            //TicketSeeder::class,     // Tickets (dependen de servicios)
        ]);
    }
}