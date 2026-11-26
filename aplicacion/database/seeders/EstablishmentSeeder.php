<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EstablishmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $criterioBusqueda = [
            'slug' => 'hotel-restaurante-las-vegas',
        ];

        $datosEstablecimiento = [
            'name' => 'Hotel Restaurante Las Vegas',
            'email' => 'restaurantelasvegas@email.com',
            'phone' => '+34 612 345 678',
            'address' => 'Rúa do Ferrocarril nº 7',
            'city' => 'Ribas de Sil',
            'postal_code' => '27320'
        ];

        DB::table('establishments')->updateOrInsert($criterioBusqueda, $datosEstablecimiento);
    }
}
