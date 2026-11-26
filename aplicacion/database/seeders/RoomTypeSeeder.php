<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $establishmentId = DB::table('establishments')
            ->where('slug', 'hotel-restaurante-las-vegas')
            ->value('id');
    
        $criterioBusqueda = [
            'establishment_id' => $establishmentId,
            'name' => 'Doble'
        ];

        $datosTipoHabitacion = [
            'capacity' => 2,
            'description' => 'Alojamiento para dos personas con camas separadas'
        ];

        DB::table('room_types')->updateOrInsert($criterioBusqueda, $datosTipoHabitacion);
    }
}
