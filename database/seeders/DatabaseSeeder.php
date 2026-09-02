<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Toda la configuración SaaS (planes, tienda demo, super admin y datos
        // de demostración) se centraliza en DemoSeeder.
        $this->call(DemoSeeder::class);
    }
}
