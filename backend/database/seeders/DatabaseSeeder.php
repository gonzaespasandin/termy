<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
        $this->call([
            ServiceSeeder::class,    // Primero los servicios
            WindowSeeder::class,     // Ventanillas (dependen de servicios)
            TotemSeeder::class,      // Totems/Perfiles (dependen de servicios)
            UserSeeder::class,       // Usuarios (dependen de ventanillas y servicios)
            //TicketSeeder::class,     // Tickets (dependen de servicios)
        ]);
    }
}